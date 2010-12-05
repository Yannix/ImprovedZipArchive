<?php
/**
 * Script encodé en UTF-8
 *
 * Auteur : julp (http://julp.developpez.com/) - adressez commentaires, suggestions, améliorations, etc
 *
 * Avertissement : cette classe est distribuée sans aucune garantie. Elle est purement expérimentale !
 * Ses auteurs ne pourront en aucun cas être tenus responsables suite à toute mésaventure lors de toute réutilisation.
 * D'autant que l'extension zip manque quelque peu de stabilité et sa documentation n'est pas à jour.
 *
 * A faire :
 * - supprimer _fileToPHP
 * - encoder/décoder les commentaires (en/de $_zip_enc) ?
 * - constructeur : vérifier les encodages ? sauf que mbstring ne les gère pas tous contrairement à iconv et cette dernière ne le permet pas directement (à moins d'un test avec iconv - qui renverrait alors FALSE)
 * - encodage php/fs :
 *     > _add_option : options add_path et remove_path (php => fs) ?
 *     > addPattern : $pattern (php => fs) ?
 *     > addGlob :  $pattern (php => fs) ?
 *     > addRecursive : $directory (php => fs) ?
 *
 * Notes :
 * - le contenu des fichiers est intouché (à l'ajout au zip comme à l'extraction du zip)
 **/
class ImprovedZipArchive extends ZipArchive implements Iterator, Countable
{
    const ENC_OLD_EUROPEAN = 'IBM850'; # CP850
    const ENC_OLD_NON_EUROPEAN = 'IBM437'; # CP437
    //const ENC_NEW = 'UTF-8';
    const ENC_DEFAULT = self::ENC_OLD_EUROPEAN;

    protected $_fs_enc;      // File System encoding
    protected $_php_int_enc; // PHP encoding
    protected $_zip_int_enc; // ZIP archive encoding

    protected $_it_pos = 0;  // Internal position of the iterator

    /**
     * (visibilité resteinte)
     * Constructeur, voir méthodes read, overwrite, create
     *
     * Paramètres :
     * + $name : chemin de l'archive à ouvrir, créer ou écraser
     * + $mode : le mode d'ouverture de l'archive (cf constantes ZipArchive::[CREATE|OVERWRITE|EXCL|CHECKCONS])
     * + $fs_enc : encodage du système de fichiers (si valeur fausse, déterminé par la fonction mb_internal_encoding - ISO-8859-1 serait un meilleur choix ?)
     * + $php_enc : encodage de l'application PHP (si valeur fausse, déterminé par la fonction mb_internal_encoding)
     * + $zip_enc : encodage de l'archive ZIP
     **/
    protected function __construct($name, $mode, $fs_enc, $php_enc, $zip_enc)
    {
        static $errors = array(
            self::ER_OK          => 'Aucune erreur',
            self::ER_MULTIDISK   => 'Archives multi-disques non supportée',
            self::ER_RENAME      => 'Erreur lors de la modification du nom du fichier temporaire',
            self::ER_CLOSE       => "Erreur lors de la fermeture de l'archive",
            self::ER_SEEK        => 'Erreur de déplacement',
            self::ER_READ        => 'Erreur de lecture',
            self::ER_WRITE       => "Erreur d'écriture",
            self::ER_CRC         => 'Erreur dans la somme CRC',
            self::ER_ZIPCLOSED   => "L'archive ZIP est fermée",
            self::ER_NOENT       => 'Fichier inexistant',
            self::ER_EXISTS      => 'Le fichier est déjà présent',
            self::ER_OPEN        => 'Ne peut ouvrir le fichier',
            self::ER_TMPOPEN     => 'Erreur lors de la création du fichier temporaire',
            self::ER_ZLIB        => 'Erreur interne liée à la librairie Zlib',
            self::ER_MEMORY      => "Erreur d'allocation dynamique de mémoire",
            self::ER_CHANGED     => "L'entrée a été modifiée",
            self::ER_COMPNOTSUPP => 'Méthode de compression non supportée',
            self::ER_EOF         => 'Fin de fichier rencontrée prématurément',
            self::ER_INVAL       => 'Argument invalide',
            self::ER_NOZIP       => "Ne s'agit pas d'une archive au format ZIP",
            self::ER_INTERNAL    => 'Erreur interne',
            self::ER_INCONS      => 'Archive inconsistente',
            self::ER_REMOVE      => 'Ne peut supprimer un fichier',
            self::ER_DELETED     => 'Entrée supprimée'
        );

        $this->_zip_int_enc = $zip_enc;

        if (!$php_enc) {
            $this->_php_int_enc = mb_internal_encoding();
        } else {
            if (!mb_internal_encoding($php_enc)) {
                throw new Exception(sprintf('Unknown encoding "%s"', $php_enc));
            }
            $this->_php_int_enc = $php_enc;
        }

        if (!$fs_enc) {
            $this->_fs_enc = mb_internal_encoding();
        } else {
            $this->_fs_enc = $fs_enc;
        }

        if ($ret = $this->open($this->_phpToFs($name), $mode) !== TRUE) {
            throw new Exception($this->_fileToPHP($errors[$ret]), $ret); // TODO
        }
    }

    /**
     * Ouvrir une archive en lecture seule
     *
     * Paramètres :
     * + $name : chemin de l'archive à ouvrir, créer ou écraser
     * - $fs_enc : encodage du système de fichiers (valeur par défaut gérée par le constructeur)
     * - $php_enc : encodage de l'application PHP (valeur par défaut gérée par le constructeur)
     * - $zip_enc : encodage de l'archive ZIP (valeur par défaut self::ENC_DEFAULT)
     *
     * Retour : un objet __CLASS__
     **/
    public static function read($name, $fs_enc = '', $php_enc = '', $zip_enc = self::ENC_DEFAULT)
    {
        $class = __CLASS__;
        return new $class($name, 0, $fs_enc, $php_enc, $zip_enc);
    }

    /**
     * Ouvrir une archive en mode de création exclusif (ie échouera si le fichier existe déjà)
     *
     * Paramètres :
     * + $name : chemin de l'archive à ouvrir, créer ou écraser
     * - $fs_enc : encodage du système de fichiers (valeur par défaut gérée par le constructeur)
     * - $php_enc : encodage de l'application PHP (valeur par défaut gérée par le constructeur)
     * - $zip_enc : encodage de l'archive ZIP (valeur par défaut self::ENC_DEFAULT)
     *
     * Retour : un objet __CLASS__
     **/
    public static function create($name, $fs_enc = '', $php_enc = '', $zip_enc = self::ENC_DEFAULT)
    {
        $class = __CLASS__;
        return new $class($name, self::CREATE | self::EXCL, $fs_enc, $php_enc, $zip_enc);
    }

    /**
     * Ouvrir une archive en mode écriture (un fichier de même nom se verrait écrasé)
     *
     * Paramètres :
     * + $name : chemin de l'archive à ouvrir, créer ou écraser
     * - $fs_enc : encodage du système de fichiers (valeur par défaut gérée par le constructeur)
     * - $php_enc : encodage de l'application PHP (valeur par défaut gérée par le constructeur)
     * - $zip_enc : encodage de l'archive ZIP (valeur par défaut self::ENC_DEFAULT)
     *
     * Retour : un objet __CLASS__
     **/
    public static function overwrite($name, $fs_enc = '', $php_enc = '', $zip_enc = self::ENC_DEFAULT)
    {
        $class = __CLASS__;
        return new $class($name, self::OVERWRITE, $fs_enc, $php_enc, $zip_enc);
    }

    /**
     * (visibilité resteinte)
     * Aide à la conversion d'encodage des chaînes
     *
     * Paramètres :
     * + $from : encodage d'origine
     * + $to : encodage désiré
     * + $string : la chaîne à convertir
     *
     * Retour : la chaîne convertie
     **/
    protected static function _iconv_helper($from, $to, $string)
    {
        if (($ret = iconv($from, $to, $string)) === FALSE) {
            throw new Exception(sprintf('Illegal character in input string or due to the conversion from "%s" to "%s"', $from, $to));
        }

        return $ret;
    }

    /**
     * (visibilité resteinte)
     * Conversion d'encodage des chaînes file => PHP
     *
     * Paramètres :
     * + $string : la chaîne à convertir, d'origine UTF-8
     *
     * Retour : la chaîne convertie, destinée à PHP
     **/
    protected function _fileToPHP($string)
    {
        return self::_iconv_helper('UTF-8', $this->_php_int_enc . '//TRANSLIT', $string);
    }

    /**
     * (visibilité resteinte)
     * Conversion d'encodage des chaînes PHP => ZIP
     *
     * Paramètres :
     * + $string : la chaîne à convertir, d'origine PHP
     *
     * Retour : la chaîne convertie, destinée à zip
     **/
    protected function _phpToZip($string)
    {
        return self::_iconv_helper($this->_php_int_enc, $this->_zip_int_enc, $string);
    }

    /**
     * (visibilité resteinte)
     * Conversion d'encodage des chaînes zip => PHP
     *
     * Paramètres :
     * + $string : la chaîne à convertir, d'origine zip
     *
     * Retour : la chaîne convertie, destinée à PHP
     **/
    protected function _zipToPHP($string)
    {
        return self::_iconv_helper($this->_zip_int_enc, $this->_php_int_enc, $string);
    }

    /**
     * (visibilité resteinte)
     * Conversion d'encodage des chaînes zip => système de fichiers (extraction des fichiers du ZIP)
     *
     * Paramètres :
     * + $string : la chaîne à convertir, d'origine zip
     *
     * Retour : la chaîne convertie, destinée au système (de fichiers)
     **/
    protected function _zipToFs($string)
    {
        return self::_iconv_helper($this->_zip_int_enc, $this->_fs_enc, $string);
    }

    /**
     * (visibilité resteinte)
     * Conversion d'encodage des chaînes système de fichiers => zip (ajout des fichiers au ZIP)
     *
     * Paramètres :
     * + $string : la chaîne à convertir, d'origine système (de fichiers)
     *
     * Retour : la chaîne convertie, destinée à zip
     **/
    protected function _fsToZip($string)
    {
        return self::_iconv_helper($this->_fs_enc, $this->_zip_int_enc, $string);
    }

    /**
     * (visibilité resteinte)
     * Conversion d'encodage des chaînes PHP => système de fichiers
     *
     * Paramètres :
     * + $string : la chaîne à convertir, d'origine PHP
     *
     * Retour : la chaîne convertie, destinée au système (de fichiers)
     **/
    protected function _phpToFs($string)
    {
        return self::_iconv_helper($this->_php_int_enc, $this->_fs_enc, $string);
    }

    /**
     * (visibilité resteinte)
     * Conversion d'encodage des chaînes système de fichiers => PHP
     *
     * Paramètres :
     * + $string : la chaîne à convertir, d'origine système (de fichiers)
     *
     * Retour : la chaîne convertie, destinée à PHP
     **/
    protected function _fsToPHP($string)
    {
        return self::_iconv_helper($this->_fs_enc, $this->_php_int_enc, $string);
    }

    /**
     * Ajouter un fichier à l'archive.
     *
     * Paramètres :
     * + $filename : le nom du fichier à ajouter
     * - $localname : son nom dans l'archive s'il doit être différent
     * - $start : inutilisé par ZipArchive::addFile
     * - $length : inutilisé par ZipArchive::addFile
     *
     * Retour : FALSE si l'opération échoue sinon TRUE
     **/
    public function addFile($filename, $localname = '', $start = 0, $length = 0)
    {
        if ($localname === '') { // === pour permettre '0' comme nom de fichier
            $localname = $this->_phpToZip($filename);
        } else {
            $localname = $this->_phpToZip($localname);
        }

        return parent::addFile($this->_phpToFs($filename), $localname);
    }

    /**
     * (surcouche)
     * Ajouter un fichier à l'archive à partir d'une chaîne.
     *
     * Paramètres :
     * + $name : nom sous lequel figurera le fichier au sein de l'archive
     * + $content : le contenu de ce fichier
     *
     * Retour : FALSE si l'opération échoue sinon TRUE
     **/
    public function addFromString($name, $content)
    {
        return parent::addFromString($this->_phpToZip($name), $content);
    }

    /**
     * (surcouche)
     * Récupérer le contenu d'une entrée à partir de son nom
     *
     * Paramètres :
     * + $name : nom de l'entrée dont on souhaite obtenir le contenu
     * - $length : spécifie le nombre de caractères maximal à renvoyer (une valeur < 1 a pour effet de rendre cette fonctionnalité inopérante, ie en renvoyer tout le contenu)
     * - $flags : une des options self::FL_[COMPRESSED|UNCHANGED] (valeur par défaut : 0)
     *
     * Retour : FALSE si une erreur est survenue ou alors le contenu du fichier
     **/
    public function getFromName($name, $length = 0, $flags = 0)
    {
        return parent::getFromName($this->_phpToZip($name), $length, $flags);
    }

    /**
     * (surcouche)
     * Supprimer un fichier de l'archive à partir de son nom
     *
     * Paramètres :
     * + $name : nom de l'entrée à supprimer
     *
     * Retour : FALSE si la suppression a échoué et TRUE dans le cas contraire
     **/
    public function deleteName($name)
    {
        return parent::deleteName($this->_phpToZip($name));
    }

    /**
     * (surcouche)
     * Ajouter un répertoire vide à l'archive
     *
     * Paramètres :
     * + $name : le nom du répertoire à ajouter
     *
     * Retour : FALSE en cas d'échec et TRUE dans le cas contraire
     **/
    public function addEmptyDir($name)
    {
        return parent::addEmptyDir($this->_phpToZip($name));
    }

    /**
     * (surcouche)
     * Obtenir le nom d'une entrée à partir de son indice
     *
     * Paramètres :
     * + $index : indice du fichier ciblé (compris entre 0 et $this->numFiles - 1 inclus)
     * - $flags : self::FL_UNCHANGED pour obtenir le commentaire d'origine (valeur par défaut : 0)
     *
     * Retour : FALSE en cas d'erreur et le nom du fichier dans le cas contraire
     **/
    public function getNameIndex($index, $flags = 0)
    {
        return $this->_zipToPHP(parent::getNameIndex($index, $flags));
    }

    /**
     * (surcouche)
     * Obtenir l'indice d'une entrée particulière dans l'archive
     *
     * Paramètres :
     * + $name : nom de l'entrée dont on souhaite obtenir le contenu
     * - $flags : une des options self::FL_[NOCASE|NODIR] (valeur par défaut : 0 - aucune option)
     *
     * Retour : FALSE si une erreur est survenue ou alors l'indice de l'entrée dans l'archive
     **/
    public function locateName($name, $flags = 0)
    {
        return parent::locateName($this->_phpToZip($name), $flags);
    }

    /**
     * (surcouche)
     * Renommer le fichier indiqué par l'intermédiaire de son indice
     *
     * Paramètres :
     * + $index : indice du fichier ciblé (compris entre 0 et $this->numFiles - 1 inclus)
     * + $name : le nouveau nom à donner à ce fichier
     *
     * Retour : TRUE si aucune erreur n'est survenue et FALSE dans le cas contraire
     **/
    public function renameIndex($index, $name)
    {
        return parent::renameIndex($index, $this->_phpToZip($name));
    }

    /**
     * (surcouche)
     * Renommer le fichier indiqué à partir de son nom courant
     *
     * Paramètres :
     * + $old_name : nom actuel du fichier à renommer
     * + $new_name : son nouveau nom
     *
     * Retour : TRUE si aucune erreur ne s'est produite sinon FALSE
     **/
    public function renameName($old_name, $new_name)
    {
        return parent::renameName($this->_phpToZip($old_name), $this->_phpToZip($new_name));
    }

    /**
     * (surcouche)
     * Changer le commentaire d'une entrée de l'archive à l'aide de son nom
     *
     * Paramètres :
     * + $name : nom du fichier visé dans l'archive
     * + $comment : le nouveau commentaire à associer à ce fichier
     *
     * Retour : FALSE si l'opération échoue et TRUE dans le cas contraire
     **/
    public function setCommentName($name, $comment)
    {
        return parent::setCommentName($this->_phpToZip($name), $comment);
    }

    /**
     * (visibilité resteinte)
     * Fonction de traitement des valeurs retournées par les méthodes émulant stat afin de réencoder ce qui doit l'être
     *
     * Paramètres :
     * + $ret : la valeur renvoyée par les méthodes émulant stat
     *
     * Retour : la valeur d'origine mais, dans le cas normal (tableau stat), la valeur name est réencodée vers l'encodage du script
     **/
    private function _decodeStatResult($ret)
    {
        if (is_array($ret) && isset($ret['name'])) {
            $ret['name'] = $this->_zipToPHP($ret['name']);
        }

        return $ret;
    }

    /**
     * (surcouche)
     * Obtenir les informations relatives à une entrée à l'aide de son nom (assimilé à la fonction stat)
     *
     * Paramètres :
     * + $name : le nom de l'entrée visée
     * - $flags : une des options self::FL_[NOCASE|NODIR|UNCHANGED] (valeur par défaut : 0)
     *
     * Retour : FALSE en cas d'échec ou bien un tableau associatif de la forme :
     *     Array (
     *         'name' => nom du fichier
     *         'index' => indice de l'entrée au sein de l'archive
     *         'crc' => sa somme de contrôle
     *         'size' => taille du fichier non compressé
     *         'mtime' => timestamp représentant la date de dernière modification du fichier
     *         'comp_size' => taille du fichier compressé
     *         'comp_method' => méthode de compression employée (voir les constantes de classe CM_)
     *     )
     **/
    public function statName($name, $flags = 0)
    {
        return $this->_decodeStatResult(parent::statName($this->_phpToZip($name), $flags));
    }

    /**
     * (surcouche)
     * Obtenir les informations relatives à une entrée par son indice (assimilé à la fonction stat)
     *
     * Paramètres :
     * + $index : indice du fichier concerné (compris entre 0 et $this->numFiles - 1 inclus)
     * - $flags : une des options self::FL_[NOCASE|NODIR|UNCHANGED] (valeur par défaut : 0)
     *
     * Retour : FALSE en cas d'échec ou bien un tableau associatif de la forme :
     *     Array (
     *         'name' => nom du fichier
     *         'index' => indice de l'entrée au sein de l'archive
     *         'crc' => sa somme de contrôle
     *         'size' => taille du fichier non compressé
     *         'mtime' => timestamp représentant la date de dernière modification du fichier
     *         'comp_size' => taille du fichier compressé
     *         'comp_method' => méthode de compression employée (voir les constantes de classe CM_)
     *     )
     **/
    public function statIndex($index, $flags = 0)
    {
        return $this->_decodeStatResult(parent::statIndex($index, $flags));
    }

    /**
     * (surcouche)
     * Annuler toutes les modifications apportées sur l'entrée indiquée par son nom
     *
     * Paramètres :
     * + $name : nom du fichier concerné par cette annulation
     *
     * Retour : TRUE si l'opération a pu être menée à bien et FALSE en cas d'erreur
     **/
    public function unchangeName($name)
    {
        return parent::unchangeName($this->_phpToZip($name));
    }

    /**
     * (surcouche)
     * Obtenir le commentaire associé à un fichier à l'aide du nom de celle-ci
     *
     * Paramètres :
     * + $name : nom de l'entrée ciblée
     * - $flags : self::FL_UNCHANGED pour obtenir le commentaire d'origine (valeur par défaut : 0)
     *
     * Retour : FALSE en cas d'erreur ou une chaîne correspondant au commentaire (éventuellement vide s'il n'y a aucun commentaire)
     **/
    public function getCommentName($name, $flags = 0)
    {
        return parent::getCommentName($this->_phpToZip($name), $flags);
    }

    /**
     * (surcouche)
     * Obtenir un "descripteur de fichier" sur une entrée de l'archive de façon à pouvoir la lire comme un flux/fichier
     *
     * Paramètres :
     * + $name : nom de l'entrée
     *
     * Retour : FALSE en cas d'erreur ou alors une ressource
     **/
    public function getStream($entry)
    {
        return parent::getStream($this->_phpToZip($entry));
    }

    /**
     * Une réimplémentation de la fonction mkdir ne générant pas d'erreur sur des répertoires existants
     *
     * Paramètres :
     * + $path : le chemin à créer
     *
     * Retour : FALSE en cas d'erreur, TRUE dans le cas contraire
     **/
    public static function mkdir_p($path)
    {
        $parts = preg_split('#/|' . preg_quote(DIRECTORY_SEPARATOR) . '#', $path, -1, PREG_SPLIT_NO_EMPTY);
        $base = (mb_substr($path, 0, 1) == '/' ? '/' : '');
        foreach ($parts as $p) {
            if (!file_exists($base . $p)) {
                if (!mkdir($base . $p)) {
                    return FALSE;
                }
            } else if (!is_dir($base . $p)) {
                return FALSE;
            }
            $base .= $p . DIRECTORY_SEPARATOR;
        }

        return TRUE;
    }

    /**
     * (réécrite)
     * Extraire, tout (en l'absence du paramètre $entries) ou partie, de l'archive
     *
     * Paramètres :
     * + $destination : emplacement, sur le système de fichiers, où l'extraction aura lieu
     * - $entries : noms des seules entrées à extraire. S'il y en a qu'une il est possible d'utiliser une chaîne de caractères plutôt qu'un tableau
     *
     * Retour : TRUE pour le bon déroulement de l'opération et FALSE si une erreur est survenue
     **/
    public function extractTo($destination, $entries = NULL)
    {
        if ($entries === NULL) {
            for ($i = 0; $i < $this->numFiles; $i++) {
                if (($name = $this->getNameIndex($i)) === FALSE) { // === pour permettre '0' comme nom de fichier
                    return FALSE;
                }
                if (($content = $this->getFromIndex($i)) === FALSE) { // === pour permettre les fichiers vides ou contenant '0'
                    return FALSE;
                }
                $to = $this->_phpToFs($destination . $name);
                if (!self::mkdir_p(dirname($to))) {
                    return FALSE;
                }
                if (mb_substr($name, -1, 1) != '/') {
                    if (file_put_contents($to, $content) === FALSE) { // === pour permettre la création de fichier vide (renverrait 0)
                        return FALSE;
                    }
                }
            }
        } else {
            if (is_string($entries)) {
                $entries = array($entries);
            }
            /* Alternative : combiner le stream wrapper zip à stream_copy_to_stream */
            foreach ($entries as $entry) {
                if (($content = $this->getFromName($entry)) === FALSE) { // === pour permettre les fichiers vides ou contenant '0'
                    return FALSE;
                }
                $to = $this->_phpToFs($destination . $entry);
                if (!self::mkdir_p(dirname($to))) {
                    return FALSE;
                }
                if (mb_substr($name, -1, 1) != '/') {
                    if (file_put_contents($to, $content) === FALSE) { // === pour permettre la création de fichier vide (renverrait 0)
                        return FALSE;
                    }
                }
            }
        }

        return TRUE;
    }

    /**
     * (visibilité restreinte)
     * Méthode d'aide à l'extraction des options aux méthodes d'ajout récursives à l'archive (addPattern et addGlob)
     *
     * Paramètres :
     * + $options : le tableau d'options tel que reçu par la méthode d'origine
     * < $add_path : fixe la valeur de l'option add_path
     * < $remove_path : fixe la valeur de l'option remove_path
     * < $remove_all_path : fixe la valeur de l'option remove_all_path
     * (Voir méthodes add[Pattern|Glob] pour le rôle de chacune)
     *
     * Retour : néant
     **/
    protected function _add_options(Array $options, &$add_path, &$remove_path, &$remove_all_path)
    {
        $remove_all_path = $remove_path = $add_path = FALSE;
        if (isset($options['remove_all_path'])) {
            $remove_all_path = !!$options['remove_all_path'];
        }
        if (isset($options['remove_path']) && is_string($options['remove_path'])) {
            $remove_path = str_replace('\\', '/', $options['remove_path']);
            if (!in_array(mb_substr($add_path, -1), array('/', '\\'))) {
                $remove_path .= '/';
            }
        }
        if (isset($options['add_path']) && is_string($options['add_path'])) {
            $add_path = str_replace('\\', '/', $options['add_path']);
            if (!in_array(mb_substr($add_path, -1), array('/', '\\'))) {
                $add_path .= '/';
            }
        }
    }

    /**
     * (visilité restreinte)
     * Méthode d'aide à la détermination du "chemin" d'un fichier lors de son ajout à l'archive en fonction des options add_path, remove_path, remove_all_path
     *
     * Paramètres :
     * + $add_path : la valeur de l'option add_path
     * + $remove_path : la valeur de l'option remove_path
     * + $remove_all_path : la valeur de l'option remove_all_path
     * + $dirname : le chemin du fichier (sans son nom, donc son parent)
     * + $basename : le nom du fichier (sans son chemin)
     *
     * Retour : le chemin du fichier destiné à l'archive
     **/
    protected function _make_path($add_path, $remove_path, $remove_all_path, $dirname, $basename)
    {
        if ($add_path) {
            if ($remove_all_path) {
                $zipname = $basename;
            } else if ($remove_path && mb_strpos($dirname, $remove_path) === 0) {
                $zipname = self::strip($remove_path, $dirname . '/' . $basename);
            } else {
                $zipname = $dirname . '/' . $basename;
            }
            $zipname = $add_path . $zipname;
        } else {
            $zipname = $dirname . '/' . $basename;
        }

        return $zipname;
    }

    /**
     * Supprime, s'il est présent, un préfixe donné à une chaîne (ex : strip('abc', 'abcdef') renverra 'def')
     *
     * Paramètres :
     * + $prefix : le préfixe à supprimer s'il est présent
     * + $filename : la chaîne à traiter
     *
     * Retour : une chaîne où, si elle commence par $prefix, $prefix sera retiré sinon la chaîne d'origine est renvoyée
     **/
    public static function strip($prefix, $filename)
    {
        if (mb_strpos($filename, $prefix) === 0) {
            $filename = mb_substr($filename, strlen($prefix));
        }

        return $filename;
    }

    /**
     * (réécrite)
     * Ajouter, par parcours récursif d'un répertoire, tout fichier correspondant à un motif
     *
     * Paramètres :
     * + $pattern : motif (PCRE) dont les fichiers qui correspondent seront ajoutés à l'archive
     * - $path : le répertoire à parcourir (le répertoire courant par défaut)
     * - $options : un tableau associatif d'options - clés - prédéterminées (par défaut : aucune) :
     *    _ add_path : chemin à ajouter à celui des fichiers lors de leur ajout à l'archive
     *    _ remove_path : partie (préfixe) du chemin à supprimer de celui du fichier lors de son ajout (implique add_path, sera ignorée sinon)
     *    _ remove_all_path : TRUE pour supprimer le chemin du fichier (ie lui appliquer basename) (implique add_path, sera ignorée sinon)
     *
     * Exemples d'options pour x/y/stdout.txt :
     * - array('add_path' => 'a/b/', 'remove_all_path' => TRUE) : a/b/stdout.txt
     * - array('add_path' => 'a/b', 'remove_all_path' => TRUE) : a/bstdout.txt
     * - array('add_path' => 'a/b/') : a/b/x/y//stdout.txt
     * - array('add_path' => 'a/b/', 'remove_path' => 'x') : a/b/y//stdout.txt
     * - array('add_path' => 'a/b', 'remove_path' => 'x') : a/by//stdout.txt
     * - array('add_path' => 'a/b', 'remove_path' => 'x/') : a/b//stdout.txt
     * - array('remove_path' => 'x') : x/y//stdout.txt
     * - array('add_path' => '', 'remove_path' => 'x') : chaîne vide interdite pour add_path
     * - array('remove_all_path' => TRUE) : x/y//stdout.txt
     * - array('remove_path' => 'x', 'remove_all_path' => TRUE) : x/y//stdout.txt
     *
     * Retour : TRUE sinon FALSE à la première erreur rencontrée
     **/
    public function addPattern($pattern, $path = '.', $options = array())
    {
        if (!file_exists($path)) {
            throw new Exception(sprintf('"%s" does not exist', $path));
        }

        if (!is_dir($path)) {
            throw new Exception(sprintf('"%s" exists and is not a directory', $path));
        }

        $this->_add_options($options, $add_path, $remove_path, $remove_all_path);

        $iter = new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST), $pattern);
        foreach ($iter as $entry) {
            if ($entry->isDir() || $entry->isFile()) {
                $zipname = self::_make_path($add_path, $remove_path, $remove_all_path, $iter->getInnerIterator()->getPath(), $iter->getInnerIterator()->getSubPathname());
                if ($entry->isDir()) {
                    if (!$this->addEmptyDir($zipname)) {
                        return FALSE;
                    }
                } else if ($entry->isFile()) {
                    if (!$this->addFile($iter->getRealPath(), $zipname)) {
                        return FALSE;
                    }
                }
            }
        }

        return TRUE;
    }

    /**
     * (réécrite)
     * Ajouter, par parcours récursif d'un répertoire, tout fichier correspondant à un motif
     *
     * Paramètres :
     * + $pattern : motif (glob) dont les fichiers qui correspondent seront ajoutés à l'archive
     * - $flags : options (sous forme d'un masque) de glob (cf constantes GLOB_* - http://php.net/glob)
     * - $options : un tableau associatif d'options - clés - prédéterminées (par défaut : aucune) :
     *    _ add_path : chemin à ajouter à celui des fichiers lors de leur ajout à l'archive
     *    _ remove_path : partie (préfixe) du chemin à supprimer de celui du fichier lors de son ajout (implique add_path, sera ignorée sinon)
     *    _ remove_all_path : TRUE pour supprimer le chemin du fichier (ie lui appliquer basename) (implique add_path, sera ignorée sinon)
     *
     * Exemples d'options pour x/y/stdout.txt :
     * - array('add_path' => 'a/b/', 'remove_all_path' => TRUE) : a/b/stdout.txt
     * - array('add_path' => 'a/b', 'remove_all_path' => TRUE) : a/bstdout.txt
     * - array('add_path' => 'a/b/') : a/b/x/y//stdout.txt
     * - array('add_path' => 'a/b/', 'remove_path' => 'x') : a/b/y//stdout.txt
     * - array('add_path' => 'a/b', 'remove_path' => 'x') : a/by//stdout.txt
     * - array('add_path' => 'a/b', 'remove_path' => 'x/') : a/b//stdout.txt
     * - array('remove_path' => 'x') : x/y//stdout.txt
     * - array('add_path' => '', 'remove_path' => 'x') : chaîne vide interdite pour add_path
     * - array('remove_all_path' => TRUE) : x/y//stdout.txt
     * - array('remove_path' => 'x', 'remove_all_path' => TRUE) : x/y//stdout.txt
     *
     * Retour : TRUE sinon FALSE à la première erreur rencontrée
     **/
    public function addGlob($pattern, $flags = 0, $options = array())
    {
        /**
         * Nous n'utilisons pas la classe GlobIterator (SPL) dont les options sont totalement différentes
         * de la fonction glob (réellement appelée par ZipArchive->addGlob - enfin son implémentation PHP interne)
         **/
        $ret = glob($pattern, $flags);
        if ($ret === FALSE) { // ===, le tableau peut parfaitement être vide (aucun fichier ne correspond)
            return FALSE;
        } else {
            $this->_add_options($options, $add_path, $remove_path, $remove_all_path);

            foreach ($ret as $entry) {
                $entry = self::_make_path($add_path, $remove_path, $remove_all_path, dirname($entry), basename($entry));
                if (!$this->addFile($entry)) {
                    return FALSE;
                }
            }

            return TRUE;
        }
    }

    public function addRecursive($directory, $options = array())
    {
        if (!file_exists($directory)) {
            throw new Exception(sprintf('"%s" does not exist', $path));
        }

        if (!is_dir($directory)) {
            throw new Exception(sprintf('"%s" exists and is not a directory', $path));
        }

        $this->_add_options($options, $add_path, $remove_path, $remove_all_path);
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iter as $entry) {
            if ($entry->isDir() || $entry->isFile()) {
                $zipname = self::_make_path($add_path, $remove_path, $remove_all_path, $iter->getInnerIterator()->getPath(), $iter->getInnerIterator()->getSubPathname());
                if ($entry->isDir()) {
                    if (!$this->addEmptyDir($zipname)) {
                        return FALSE;
                    }
                } else if ($entry->isFile()) {
                    if (!$this->addFile($iter->getRealPath(), $zipname)) {
                        return FALSE;
                    }
                }
            }
        }

        return TRUE;
    }

    /**
     * Méthodes implémentant l'interface Iterator
     * Voir : http://php.net/manual/class.iterator.php
     **/

    public function rewind()
    {
        $this->_it_pos = 0;
    }

    public function current()
    {
        $ret = $this->statIndex($this->_it_pos);
        if (is_array($ret)) {
            $ret['comment'] = $this->getCommentIndex($this->_it_pos);
        }

        return $ret;
    }

    public function key()
    {
        return $this->_it_pos;
    }

    public function next()
    {
        $this->_it_pos++;
    }

    public function valid()
    {
        return $this->_it_pos < $this->numFiles;
    }

    /**
     * Méthodes implémentant l'interface Countable
     * Voir : http://php.net/manual/class.countable.php
     **/

    public function count()
    {
        return $this->numFiles;
    }

    /**
     * Méthode magique appelée lors d'une tentative de conversion d'un objet __CLASS__ en chaîne
     *
     * Retour : une chaîne décrivant l'objet
     **/
    public function __toString()
    {
        return sprintf(
            '%s (name: %s, file(s): %d, comment: %s, encodings: zip = %s, php = %s, file system = %s)',
            get_called_class(),
            $this->_fsToPHP($this->filename),
            $this->numFiles,
            $this->comment ? $this->comment : '-',
            $this->_zip_int_enc,
            $this->_php_int_enc,
            $this->_fs_enc
        );
    }
}
