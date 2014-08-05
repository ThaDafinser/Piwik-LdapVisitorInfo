<?php
/**
 * @author https://github.com/ThaDafinser
 */
namespace Piwik\Plugins\LdapVisitorInfo;

use Piwik\Plugin;
use Piwik\Plugins\LdapConnection\API as APILdapConnection;
use Zend\Ldap\Ldap;
use stdClass;

class LdapVisitorInfo extends Plugin
{

    const DEFAULT_SEARCH_FILTER = '(&(objectclass=user)(samAccountName=%s))';

    const DEFAULT_AVATAR_FIELD = 'thumbnailphoto';

    const DEFAULT_AVATAR_DESCRIPTION_FIELD = 'displayname';

    /**
     *
     * @see Piwik\Plugin::getListHooksRegistered
     */
    public function getListHooksRegistered()
    {
        return array(
            'Live.getExtraVisitorDetails' => 'getVisitorDetailsFromLdap'
        );
    }

    /**
     * Called by event `Live.getExtraVisitorDetails`
     *
     * @see getListHooksRegistered()
     *
     */
    public function getVisitorDetailsFromLdap(&$result)
    {
        $settings = $this->getSettings();
        
        /* @var $lastVisits \Piwik\DataTable */
        $lastVisits = $result['lastVisits'];
        
        $possibleUsernames = [];
        foreach ($lastVisits->getRows() as $visit) {
            /* @var $visit \Piwik\DataTable\Row */
            $row = $visit->getColumns();
            
            $customVariables = $row['customVariables'];
            if (is_array($customVariables)) {
                foreach ($customVariables as $customVariable) {
                    for ($i = 1; $i < 6; $i ++) {
                        if (isset($customVariable['customVariableName' . $i]) && $customVariable['customVariableName' . $i] == $settings['customVariableName']) {
                            $possibleUsernames[] = $customVariable['customVariableValue' . $i];
                        }
                    }
                }
            }
        }
        
        $possibleUsernames = array_unique($possibleUsernames);
        
        // do only something if one unique username is found!
        if (count($possibleUsernames) === 1) {
            list ($visitorAvatar, $visitorDescription) = $this->getData(array_pop($possibleUsernames));
            
            if ($visitorAvatar !== null) {
                $result['visitorAvatar'] = $visitorAvatar;
            }
            
            if ($visitorDescription != '') {
                $result['visitorDescription'] = $visitorDescription;
            }
        }
    }

    /**
     *
     * @return array
     */
    private function getSettings()
    {
        $settings = new Settings('LdapVisitorInfo');
        $settings = $settings->getSettings();
        $settingValues = [];
        foreach ($settings as $key => $setting) {
            $value = $setting->getValue();
            if ($value == '') {
                $value = null;
            }
            
            $settingValues[$key] = $value;
        }
        
        return $settingValues;
    }

    private function getData($visitorUsername)
    {
        $visitorAvatar = null;
        $visitorDescription = [];
        
        // check if LdapConnection plugin is available!
        if (! class_exists('Piwik\Plugins\LdapConnection\API')) {
            // @todo
            return [
                $visitorAvatar,
                $visitorDescription
            ];
        }
        
        $settings = $this->getSettings();
        if ($settings['searchFilter'] === null) {
            $settings['searchFilter'] = self::DEFAULT_SEARCH_FILTER;
        }
        
        $visitorDescriptionFields = $settings['visitorDescriptionFields'];
        if ($settings['searchFilter'] === null) {
            $visitorDescriptionFields = self::DEFAULT_AVATAR_DESCRIPTION_FIELD;
        }
        
        if ($settings['visitorAvatarField'] === null) {
            $settings['visitorAvatarField'] = self::DEFAULT_AVATAR_FIELD;
        }
        
        $visitorDescriptionFields = explode(',', $visitorDescriptionFields);
        foreach ($visitorDescriptionFields as $key => $field) {
            $visitorDescriptionFields[$key] = trim($field);
        }
        
        $fields = array_merge($visitorDescriptionFields, [
            $settings['visitorAvatarField']
        ]);
        
        /* @var $ldap \Zend\Ldap\Ldap */
        $ldap = APILdapConnection::getInstance()->getConnection();
        $ldap->connect();
        
        $filter = sprintf($settings['searchFilter'], $visitorUsername);
        
        $collection = $ldap->search($filter, null, Ldap::SEARCH_SCOPE_SUB, $fields);
        
        if ($collection->count() >= 1) {
            $result = $this->getEntryConverted($collection->getFirst());
            
            if (isset($result->{$settings['visitorAvatarField']})) {
                $visitorAvatar = "data:image/jpeg;base64," . base64_encode($result->{$settings['visitorAvatarField']});
            }
            
            if (isset($settings['visitorDescriptionFields'])) {
                foreach ($visitorDescriptionFields as $field) {
                    if (isset($result->{$field})) {
                        $visitorDescription[] = $field . ': ' . $result->{$field};
                    }
                }
            }
        }
        
        return [
            $visitorAvatar,
            implode(';' . "\n", $visitorDescription)
        ];
    }

    /**
     *
     * @param array $entry            
     * @return stdClass
     */
    private function getEntryConverted($entry)
    {
        if ($entry instanceof stdClass) {
            return $entry;
        }
        
        $returnObject = new stdClass();
        foreach ($entry as $attr => $value) {
            if ($attr == 'usercertificate') {
                continue;
            }
            
            $returnObject->$attr = $value;
            if (is_array($value)) {
                $returnObject->$attr = (count($value) === 1) ? $value[0] : $value;
            }
            
            if ($attr == 'memberof' && ! is_array($returnObject->$attr)) {
                $returnObject->$attr = array(
                    $returnObject->$attr
                );
            }
        }
        
        return $returnObject;
    }
}
