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
        $username = $this->getUsername($result);
        if ($username === false) {
            return;
        }
        
        list ($visitorAvatar, $visitorDescription) = $this->getData($username);
        if ($visitorAvatar !== null) {
            $result['visitorAvatar'] = $visitorAvatar;
        }
        if ($visitorDescription != '') {
            $result['visitorDescription'] = $visitorDescription;
        }
    }

    /**
     * Return the username or false
     *
     * @return string|boolean
     */
    private function getUsername($result)
    {
        if ($this->getSetting('usePiwikUserId') === true) {
            if (isset($result['userId'])) {
                return $result['userId'];
            }
            
            return false;
        }
        
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
                        if (isset($customVariable['customVariableName' . $i]) && $customVariable['customVariableName' . $i] == $this->getSetting('customVariableName')) {
                            $possibleUsernames[] = $customVariable['customVariableValue' . $i];
                        }
                    }
                }
            }
        }
        
        $possibleUsernames = array_unique($possibleUsernames);
        
        // only return if a "unique" result is found
        if (count($possibleUsernames) === 1) {
            return $possibleUsernames[0];
        }
        
        return false;
    }

    /**
     *
     * @param string $name            
     * @return mixed
     */
    private function getSetting($name)
    {
        $settings = new Settings('LdapVisitorInfo');
        $settings = $settings->getSettings();
        if (! isset($settings[$name])) {
            return null;
        }
        
        /* @var $value \Piwik\Settings\SystemSetting */
        $setting = $settings[$name];
        $value = $setting->getValue();
        if ($value == '') {
            $value = null;
        }
        
        return $value;
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
        
        $searchFilter = $this->getSetting('searchFilter');
        if ($searchFilter === null) {
            $searchFilter = self::DEFAULT_SEARCH_FILTER;
        }
        
        $visitorDescriptionFields = $this->getSetting('visitorDescriptionFields');
        if ($visitorDescriptionFields === null) {
            $visitorDescriptionFields = self::DEFAULT_AVATAR_DESCRIPTION_FIELD;
        }
        
        $visitorAvatarField = $this->getSetting('visitorAvatarField');
        if ($visitorAvatarField === null) {
            $visitorAvatarField = self::DEFAULT_AVATAR_FIELD;
        }
        
        $visitorDescriptionFields = explode(',', $visitorDescriptionFields);
        foreach ($visitorDescriptionFields as $key => $field) {
            $visitorDescriptionFields[$key] = trim($field);
        }
        
        $fields = array_merge($visitorDescriptionFields, [
            $visitorAvatarField
        ]);
        
        /* @var $ldap \Zend\Ldap\Ldap */
        $ldap = APILdapConnection::getInstance()->getConnection();
        $ldap->connect();
        
        $filter = sprintf($searchFilter, $visitorUsername);
        
        $collection = $ldap->search($filter, null, Ldap::SEARCH_SCOPE_SUB, $fields);
        
        if ($collection->count() >= 1) {
            $result = $this->getEntryConverted($collection->getFirst());
            
            if (isset($result->{$visitorAvatarField})) {
                $visitorAvatar = "data:image/jpeg;base64," . base64_encode($result->{$visitorAvatarField});
            }
            
            if (isset($visitorDescriptionFields)) {
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
