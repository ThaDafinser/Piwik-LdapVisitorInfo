<?php
/**
 * @author https://github.com/ThaDafinser
 */
namespace Piwik\Plugins\LdapVisitorInfo;

use Piwik\Settings\FieldConfig;
use Piwik\Settings\Setting;

class SystemSettings extends \Piwik\Settings\Plugin\SystemSettings
{

    /**
     *
     * @var Setting
     */
    public $usePiwikUserId;

    /**
     *
     * @var Setting
     */
    public $customVariableName;

    /**
     *
     * @var Setting
     */
    public $visitorAvatarField;

    /**
     *
     * @var Setting
     */
    public $visitorDescriptionFields;

    /**
     *
     * @var Setting
     */
    public $searchFilter;

    protected function init()
    {
        $this->usePiwikUserId = $this->makeSetting('usePiwikUserId', true, FieldConfig::TYPE_BOOL, function (FieldConfig $field) {
            $field->title = 'Use the Piwik UserId field';
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
            $field->description = 'Use the new Piwik UserId (ignore the custom variable name)';
        });
        
        $this->customVariableName = $this->makeSetting('customVariableName', 'username', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'Name of the custom variable';
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
            $field->description = 'Custom variable name where you set the user identifier (e.g. username, mail)';
        });
        
        $this->visitorAvatarField = $this->makeSetting('visitorAvatarField', 'thumbnailphoto', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'Avatar field name';
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
            $field->description = 'In AD it\'s normally thumbnailphoto';
        });
        
        $this->visitorDescriptionFields = $this->makeSetting('visitorDescriptionFields', 'displayname', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'Display field name(s)';
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
            $field->description = 'In AD it\'s you can use displayname. Define multiple fields with comma. E.g. displayname,sn,givenname,mail';
        });
        
        $this->searchFilter = $this->makeSetting('searchFilter', '(&(objectclass=user)(samAccountName=%s))', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'LDAP search filter';
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
            $field->description = 'Search for username: "(&(objectclass=user)(samAccountName=%s))". Search for E-Mail (&(objectclass=user)(mail=%s))';
        });
    }
}
