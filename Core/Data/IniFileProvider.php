<?php

namespace HedgeBot\Core\Data;

use HedgeBot\Core\HedgeBot;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/** Use the first 2 section names as subdirectory/file name for the config. */
class IniFileProvider extends Provider
{
    private $dataDirectory; // Root storage directory
    private $fileLastModification; // Files last modification times, for updating
    private $backups; // Wether to do backups or not

    private $data; // Data storage

    const STORAGE_NAME = "ini";
    const STORAGE_PARAMETERS = ["basepath", "backups"];

    /**
     * Loads data from INI formatted files into a directory, recursively.
     * This function loads data from all .ini files in the given folder.
     * It also loads the data found in all its sub-directories.
     * The files are proceeded as .ini files, but adds a useful feature to them : multi-level sections.
     * Using the '.', users will be able to define more than one level of data (useful for ordering).
     * It does not parses the UNIX hidden directories.
     *
     * @param $dir The directory to analyze.
     * @param bool $reset Wether to reset internal data structure before loading or not. Defaults to true.
     * @return bool TRUE if the data loaded successfully, FALSE otherwise.
     */
    public function loadData($dir, $reset = true)
    {
        $dirContent = scandir($dir);
        if (!$dirContent) {
            return false;
        }

        if ($reset) {
            $this->data = array();
        }

        $cfgdata = '';
        foreach ($dirContent as $file) {
            $fileData = null;
            if (is_file($dir . '/' . $file) && pathinfo($file, PATHINFO_EXTENSION) == 'ini' && $file[0] != '.') {
                $fileData = $this->loadFile($dir . '/' . $file);
                $this->fileLastModification[$dir . '/' . $file] = filemtime($dir . '/' . $file);
            } elseif (is_dir($dir . '/' . $file) && !in_array($file, array('.', '..')) && $file[0] != '.') {
                $fileData = $this->loadData($dir . '/' . $file, false);

                if ($fileData === false) {
                    HedgeBot::message('Parse error in $0 directory.', array($dir), E_WARNING);
                    return false;
                }
            }

            if (!empty($fileData) && is_array($fileData)) {
                $this->data = array_merge_recursive($this->data, $fileData);
            }
        }

        return true;
    }

    /**
     * Loads a file data and returns it.
     *
     * @param $file The file to load data from.
     * @return array|bool The data that was loaded from the file, or FALSE if any couldn't be found.
     */
    public function loadFile($file)
    {
        HedgeBot::message('Loading file "$0"...', array($file), E_DEBUG);
        $fileContent = file_get_contents($file);

        if (empty($fileContent)) {
            return array();
        }

        $data = $this->parseINIStringRecursive($fileContent);
        if ($data) {
            return $data;
        } else {
            HedgeBot::message("Cannot load data from file $0", array($file), E_WARNING);
            return false;
        }
    }

    /**
     * Parses an INI-formatted string recursively.
     * This method parses the given string as an INI format, and returns the resulting structured data.
     * It uses sections names to translate the recursivity ability: the path where the section should be is
     * defined using points as a separator.
     *
     * @param string $str The string to parse.
     * @return array The resulting data array.
     */
    public function parseINIStringRecursive($str)
    {
        $config = array();

        //Parsing string and determining recursive array
        $inidata = parse_ini_string($str, true, INI_SCANNER_RAW);
        if (!$inidata) {
            return false;
        }
        foreach ($inidata as $section => $content) {
            if (is_array($content)) {
                $section = explode('.', $section);
                //Getting reference on the config category pointed
                $edit = &$config;
                foreach ($section as $el) {
                    $edit = &$edit[$el];
                }

                $edit = str_replace('\\"', '"', $content);
            } else {
                HedgeBot::message('Orphan config parameter : $0', array($section), E_WARNING);
            }
        }

        return $config;
    }

    /**
     * Generates INI config string for recursive data.
     * This function takes configuration array passed in parameter
     * and generates an INI configuration string with recursive sections.
     *
     * @param array $data The data to be transformed.
     * @param string $root The root section.
     *                     Normally, this parameter is used by the function to recursively parse data by calling itself.
     * @return string The INI config data.
     */
    public function generateINIStringRecursive($data = null, $root = "")
    {
        $out = "";

        if ($data === null) {
            $data = $this->config;
        }

        $arrays = array();

        //Process data, saving sub-arrays, putting direct values in config.
        foreach ($data as $name => $value) {
            if (is_array($value)) {
                // Handle numeric arrays.
                $isNumeric = true;
                $flatArray = '';
                foreach ($value as $k => $v) {
                    if (!is_numeric($k)) {
                        $isNumeric = false;
                        break;
                    } else {
                        $flatArray .= $name . '[' . $k . ']=' . $this->escapeValue($v) . "\n";
                    }
                }

                if ($isNumeric) {
                    $out .= $flatArray;
                } else {
                    $arrays[$name] = $value;
                }
            } elseif (is_object($value)) {
                $arrays[$name] = $value;
            } elseif (is_bool($value)) {
                $out .= $name . '=' . ($value ? 'yes' : 'no') . "\n";
            } elseif (is_string($value) && !is_numeric($value)) {
                $out .= $name . '="' . str_replace('"', '\\"', $value) . "\"\n";
            } else {
                $out .= $name . '=' . $value . "\n";
            }
        }

        // If the flat data is empty and there isn't any sub-sections, discard the parameter
        if (empty($out) && empty($arrays)) {
            return "";
        }

        if ($root) {
            $out = '[' . $root . ']' . "\n" . $out;
        }

        $out .= "\n";

        //Processing sub-sections
        foreach ($arrays as $name => $value) {
            $out .= $this->generateINIStringRecursive($value, $root . ($root ? '.' : '') . $name) . "\n\n";
        }

        return trim($out);
    }

    /**
     * Gets a variable from the data storage.
     * This method gets a variable, scalar or complex, from the storage.
     *
     * @param string $key The key corresponding to the data to get.
     * @return bool|A|mixed|null The requested data or NULL on failure.
     */
    public function get($key = null)
    {
        if(!empty($key)) {
            $keyComponents = explode('.', $key);
        } else {
            $keyComponents = [];
        }

        $currentPath = &$this->data;
        foreach ($keyComponents as $component) {
            if (!isset($currentPath[$component])) {
                return null;
            } elseif (is_array($currentPath)) {
                $currentPath = &$currentPath[$component];
            } else {
                return false;
            }
        }

        return $currentPath;
    }

    /**
     * Sets a variable in the data storage.
     * Sets a var in the data storage, and saves instantly all the data.
     * TODO: Save only the relevant part instead of all the data ?
     *
     * @param string $key The key under which to save the data.
     * @param mixed $data The value to save. Could be a complex structure like an array.
     * @return bool TRUE if the data has been saved, FALSE otherwise.
     */
    public function set($key, $data)
    {
        $keyComponents = explode('.', $key);

        $varName = array_pop($keyComponents);

        $currentPath = &$this->data;
        foreach ($keyComponents as $component) {
            if (!isset($currentPath[$component])) {
                $currentPath[$component] = array();
            }

            if (is_array($currentPath[$component])) {
                $currentPath = &$currentPath[$component];
            } else {
                return false;
            }
        }

        $currentPath[$varName] = $data;
        $this->writeData();
        return true;
    }

    /**
     * Removes a variable from the data storage.
     * Removes a var from the data storage and instantly saves all the data.
     * 
     * @param string $key The key to delete.
     * 
     * @return bool true if the data has been deleted, false otherwise.
     */
    public function remove($key = null)
    {
        $keyComponents = explode('.', $key);

        $varName = array_pop($keyComponents);

        $currentPath = &$this->data;
        foreach ($keyComponents as $component) {
            if (!isset($currentPath[$component])) {
                $currentPath[$component] = array();
            }

            if (is_array($currentPath[$component])) {
                $currentPath = &$currentPath[$component];
            } else {
                return false;
            }
        }

        unset($currentPath[$varName]);
        $this->writeData();
        return true;
    }

    /**
     * Checks if there is an update to the data files, and does it if necessary.
     *
     * @param $dir
     * @return bool|void
     */
    public function checkUpdate($dir = null)
    {
        if (empty($dir)) {
            $dir = $this->dataDirectory;
        }

        $dirContent = scandir($dir);
        if (!$dirContent) {
            return;
        }

        $updated = false;
        foreach ($dirContent as $file) {
            if (is_file($dir . '/' . $file) && pathinfo($file, PATHINFO_EXTENSION) == 'ini') {
                $fileLastModification = filemtime($dir . '/' . $file);

                if (!isset($this->fileLastModification[$dir . '/' . $file])
                    || $this->fileLastModification[$dir . '/' . $file] < $fileLastModification) {
                    $updated = true;
                    $data = $this->loadFile($dir . '/' . $file);
                    $this->data = array_replace_recursive($this->data, $data);
                    $this->fileLastModification[$dir . '/' . $file] = filemtime($dir . '/' . $file);
                }
            } elseif (is_dir($dir . '/' . $file) && !in_array($file, array('.', '..')) && $file[0] != '.') {
                $this->checkUpdate($dir . '/' . $file);
            }
        }

        return $updated;
    }

    /**
     * Writes the stored data to the disk.
     * Writes the stored data to the disk. It will create multiple directories/files depending on the first
     * 2 sections of the path to the data if it contains more than 2 levels of nesting. Else, just the first level
     * of nesting will be used as filename.
     * Parameters without a section will be put in a main.ini file on the conf root.
     *
     * @return bool true if the writing of the data succeeded, false otherwise.
     */
    public function writeData()
    {
        if ($this->readonly) {
            return false;
        }

        $iniData = $this->generateINIStringRecursive($this->data);
        $flatData = parse_ini_string($iniData, true);

        $sections = array();
        $root = array(); // Root section

        // Separate each section.
        foreach ($flatData as $key => $data) {
            // An array can be a regular numeric array as well as a section
            if (is_array($data)) {
                $isNumeric = true;
                foreach ($data as $k => $v) {
                    if (!is_numeric($key)) {
                        $isNumeric = false;
                        break;
                    }
                }

                if (!$isNumeric) {
                    $sections[$key] = $data;
                } else {
                    $root[$key] = $data;
                }
            } else {
                $root[$key] = $data;
            }
        }

        // Backing up old data
        if ($this->backups) {
            $this->backupData();
        }

        // Remove all files in the destination directory
        $this->wipeDataDirectory();

        // Generate files
        if (!$this->emptyRecursive($root)) {
            $iniRoot = $this->generateINIStringRecursive($root);
            file_put_contents($this->dataDirectory . '/root.ini', $iniRoot);
        }

        foreach ($sections as $name => $section) {
            $sectionComponents = explode('.', strtolower($name));
            $filename = "";

            if (count($sectionComponents) == 1) {
                $filename = $sectionComponents[0] . ".ini";
            } elseif (count($sectionComponents) >= 2) {
                if (!is_dir($this->dataDirectory . '/' . $sectionComponents[0])) {
                    mkdir($this->dataDirectory . '/' . $sectionComponents[0]);
                }

                $filename = $sectionComponents[0] . "/" . $sectionComponents[1] . ".ini";
            }

            $iniSection = $this->generateINIStringRecursive($section);
            $iniSection = "[" . $name . "]\n" . $iniSection;

            // If there is already a file, add a line jump before writing
            if (is_file($this->dataDirectory . '/' . $filename)) {
                $iniSection = "\n\n" . $iniSection;
            }

            file_put_contents($this->dataDirectory . '/' . $filename, $iniSection, FILE_APPEND);
        }
    }

    /**
     * Loads data from the file store in the specified directory.
     *
     * @param $parameters
     * @return bool
     */
    public function connect($parameters)
    {
        $this->backups = false;

        // If we're given an object configuration (from the boostrapping storage), load the location from it
        $location = null;
        if (is_object($parameters)) {
            $location = $parameters->basepath;

            if (isset($parameters->backups) && HedgeBot::parseBool($parameters->backups)) {
                $this->backups = true;
            }
        } else {
            $location = $parameters;
        }

        HedgeBot::message('Connecting to INI file storage at directory "$0"', array($location), E_DEBUG);

        if (substr($location, -1) == '/') {
            $location = substr($location, 0, -1);
        }

        if (is_dir($location)) {
            $this->dataDirectory = $location;
        } elseif (is_file($location)) {
            $this->dataDirectory = pathinfo($location, PATHINFO_DIRNAME);
        } else {
            HedgeBot::message("The specified directory for file data storage doesn't exist.", null, E_WARNING);
            return false;
        }

        $this->loadData($this->dataDirectory);

        return true;
    }

    /** Sets backup function.
     * Sets wether to do backups of the data when updating or not.
     * @param bool $backups TRUE to do backups, FALSE to not.
     */
    public function setBackups($backups)
    {
        $this->backups = $backups;
    }

    /**
     * Backs up the data directory.
     * Backs up the data directory to a backup directory. This is a recursive function,
     * which will take the current subdir to perform recursive copy.
     *
     * @param string $currentDir
     */
    private function backupData($currentDir = "")
    {
        $baseDir = $this->dataDirectory . "/" . $currentDir;
        $backupDir = $this->dataDirectory . "/.backup/" . $currentDir;

        $contents = scandir($baseDir);
        foreach ($contents as $file) {
            if (!in_array($file, array('.', '..', '.backup'))) {
                if (!is_dir($backupDir)) {
                    mkdir($backupDir);
                }

                if (is_dir($baseDir . '/' . $file)) {
                    $this->backupData($currentDir . '/' . $file);
                } else {
                    copy($baseDir . '/' . $file, $backupDir . '/' . $file);
                }
            }
        }
    }

    /** Wipes the content of the data directory.
     * This method deletes all the files and folders in the current directory.
     */
    private function wipeDataDirectory()
    {
        $wipeDir = $this->dataDirectory;
        if (!empty($dir)) {
            $wipeDir .= $dir;
        }

        $iterator = new RecursiveDirectoryIterator($wipeDir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator(
            $iterator,
            RecursiveIteratorIterator::CHILD_FIRST | RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (strpos($file->getPathname(), '.backup') === false) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }
        }
    }

    /**
     * Checks if an array has no scalar value in it, recursively.
     * This method checks recursively the content of an array, searching if it has any scalar value saved in it.
     * If it hasn't, it returns TRUE (behaves like empty()).
     *
     * @param array $data
     * @return bool
     */
    private function emptyRecursive($data)
    {
        if (!is_array($data)) {
            return empty($data);
        }

        $empty = true;
        foreach ($data as $el) {
            if (!is_array($el)) {
                $empty = $empty && empty($el);
            } else {
                $empty = $empty && $this->emptyRecursive($el);
            }
        }

        return $empty;
    }

    /**
     * Escapes values to store them safely in the file.
     * @param  mixed $value The value to escape
     * @return string        The escaped value
     */
    private function escapeValue($value)
    {
        // Convert int to string
        if (is_numeric($value)) {
            return (string)$value;
        }

        if (is_string($value)) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }

        return $value;
    }
}
