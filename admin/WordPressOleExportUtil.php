<?php
class WordPressOleExportUtil {

    /**
     * @param $key
     * @param $value
     */
    public static function updateOption($key, $value) {
        $options = self::getOptions();

        if (empty($options)) {
            $options = [];
        }

        $options[$key] = $value;
        update_option('oleexport', $options);
    }

    /**
     * @return mixed|void
     */
    public static function getOptions() {
        return get_option('oleexport');
    }

    /**
     * @param $key
     * @return bool|mixed
     */
    public static function getOption($key) {
        $options = self::getOptions();
        if (empty($options) || !isset($options[$key])) {
            return false;
        }
        return $options[$key];
    }

    /**
     * @return array
     */
    public static function getOleExportDrivers() {
        $drivers = [];
        $drivers = array_merge($drivers,self::_getOleExportDrivers(dirname( __FILE__ ).'/../oledata/ch/fugu/oledata/driver/wordpress/*.php', true));
        $drivers = array_merge($drivers,self::_getOleExportDrivers(dirname( __FILE__ ).'/../../../oleexport_customdriver/*.php'));
        return $drivers;
    }

    protected static function _getOleExportDrivers($globPattern, $isLib=false) {
        $drivers = [];
        foreach (glob($globPattern) as $path) {
            $className = pathinfo($path, PATHINFO_FILENAME);
            if($isLib) {
                $className = 'ch\\fugu\\oledata\\driver\\wordpress\\' . $className;
            }
            else {
                include($path);
            }
            $reflectionClass = new ReflectionClass($className);
            if ($reflectionClass->isInstantiable() && $reflectionClass->isSubclassOf('ch\\fugu\\oledata\\driver\\wordpress\\AbstractWordPressOleDriver')) {
                $driver = new $className();
                $drivers[] = (object)array(
                    'className' => $className,
                    'displayName' => $driver->getDisplayName(),
                    'activePluginName' => $driver->getActivePluginName(),
                    'eventPostTypes' => $driver->getEventPostTypes()
                );
            }
        }
        return $drivers;
    }
}
