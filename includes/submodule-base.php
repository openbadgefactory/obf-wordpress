<?php
/**
 * Open Badge Factory Submodule Base Class
 *
 * @package Open Badge Factory WP
 * @author Discendum Oy
 * @license http://www.gnu.org/licenses/agpl.txt GNU AGPL v3.0
 * @link https://openbadgefactory.com
 */
class Obf_SubmoduleBase {
    /**
     * Plugin Basename
     *
     * @var string
     */
    public $basename = '';
    
    /**
     * Get the name of the submodule.
     * 
     * @return string The name
     */
    public function get_submodule_name() {
        return basename(dirname($this->basename));
    }
    /**
     * Check if submodule is not yet activated.
     * 
     * @return boolean True if not activated.
     */
    public function is_not_activated() {
        global $GLOBALS;
        $settings = $GLOBALS['badgeos']->get_settings();
        if (array_key_exists('activated_submodules', $settings) && in_array($this->get_submodule_name(), $settings['activated_submodules'])) {
            return false;
        }
        return true;
    }
    /**
     * Update submodule activation info.
     * 
     * @param boolean $activate To activate or to disactivate. False to disactivate.
     */
    public function set_is_activated($activate = true) {
        global $GLOBALS;
        $settings = $GLOBALS['badgeos']->get_settings();
        $activated = array_key_exists('activated_submodules', $settings) ? $settings['activated_submodules'] : array();
        if ($activate) {
            if (!in_array($this->get_submodule_name(), $activated)) {
                $activated[] = $this->get_submodule_name();
            }
        } else {
            foreach (array_keys($activated, $this->get_submodule_name(), true) as $key) {
                unset($activated[$key]);
            }
        }
        $GLOBALS['badgeos']->update_setting('activated_submodules', $activated);
    }
    /**
     * Activate if not already activated.
     */
    public function maybe_activate() {
        if (method_exists($this, 'meets_requirements') &&
            method_exists($this, 'activate') && 
            $this->meets_requirements() && $this->is_not_activated()) {
            $this->activate();
            $this->set_is_activated(true);
        }
    }
}