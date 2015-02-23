<?php
/**
 * @author https://github.com/ThaDafinser
 */
namespace Piwik\Plugins\LdapVisitorInfo;

use Piwik\Settings\SystemSetting;
use Piwik\Settings\Setting;

class Settings extends \Piwik\Plugin\Settings
{

    protected function init()
    {
        $setting = new SystemSetting('usePiwikUserId', 'Use the new userId');
        $setting->readableByCurrentUser = true;
        $setting->type = self::TYPE_BOOL;
        $setting->defaultValue = false;
        $setting->inlineHelp = 'Use the new Piwik UserId (ignore all custom variables)';
        $this->addSetting($setting);
        
        $setting = new SystemSetting('customVariableName', 'Name of the custom variable');
        $setting->readableByCurrentUser = true;
        $setting->type = self::TYPE_STRING;
        $setting->defaultValue = 'username';
        $setting->inlineHelp = 'Custom variable name where you set the user identifier (e.g. username, mail)';
        $this->addSetting($setting);
        
        $setting = new SystemSetting('visitorAvatarField', 'Avatar field name');
        $setting->readableByCurrentUser = true;
        $setting->type = self::TYPE_STRING;
        $setting->defaultValue = 'thumbnailphoto';
        $setting->inlineHelp = 'In AD it\'s normally thumbnailphoto';
        $this->addSetting($setting);
        
        $setting = new SystemSetting('visitorDescriptionFields', 'Display field name(s)');
        $setting->readableByCurrentUser = true;
        $setting->type = self::TYPE_STRING;
        $setting->defaultValue = 'displayname';
        $setting->inlineHelp = 'In AD it\'s you can use displayname. Define multiple fields with comma. E.g. displayname,sn,givenname,mail';
        $this->addSetting($setting);
        
        $setting = new SystemSetting('searchFilter', 'LDAP search filter');
        $setting->readableByCurrentUser = true;
        $setting->type = self::TYPE_STRING;
        $setting->defaultValue = '(&(objectclass=user)(samAccountName=%s))';
        $setting->inlineHelp = 'Search for username: "(&(objectclass=user)(samAccountName=%s))". Search for E-Mail (&(objectclass=user)(mail=%s))';
        $setting->transform = function ($value, Setting $setting) {
            return (string) $value;
        };
        $setting->validate = function ($value, Setting $setting) {};
        
        $this->addSetting($setting);
    }
}
