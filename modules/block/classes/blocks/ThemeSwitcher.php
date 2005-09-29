<?php
/**
 * This block allows you to switch between themes.
 *
 * @package block
 * @author  Demian Turner <demian@phpkitchen.com>
 * @version $Revision: 1.1 $
 * @since   PHP 4.1
 */
class ThemeSwitcher
{
    function init()
    {
        $this->aThemes = SGL_Util::getAllThemes();
        $req = & SGL_Request::singleton();
        
        $theme = $req->get('frmThemeSwitcher');
        if (!is_null($theme)) {
            if (in_array($theme, $this->aThemes)) {
                $_SESSION['aPrefs']['theme'] = $theme;
            }
        }
        return $this->getBlockContent();
    }

    function getBlockContent()
    {
        $options = SGL_Output::generateSelect($this->aThemes, $_SESSION['aPrefs']['theme']);

        $req = & SGL_Request::singleton();        
        $url = SGL_Url::makeLink() . 'frmThemeSwitcher/';
        $html = <<< HTML
        <p>Change the current theme:</p>
        <form id="frmSwitcher" action="">
        <select id="frmThemeSwitcher" onChange="document.location.href='$url' + getSelectedValue(document.getElementById('frmSwitcher').frmThemeSwitcher) + '/';">
        $options
        </select>
        </form>
        <p>&nbsp;</p>
HTML;
        return $html;
    }
}
?>