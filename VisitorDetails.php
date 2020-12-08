<?php
/**
 * @author https://github.com/ThaDafinser
 * @author https://github.com/dshiryaev-plesk
 */
namespace Piwik\Plugins\LdapVisitorInfo;

use Piwik\DataTable;
use Piwik\Plugins\LdapConnection\API as APILdapConnection;
use Piwik\Plugins\Live\VisitorDetailsAbstract;
use Piwik\Settings\Setting;
use Zend\Ldap\Ldap;
use stdClass;

class VisitorDetails extends VisitorDetailsAbstract
{
    const DEFAULT_SEARCH_FILTER = '(&(objectclass=user)(samAccountName=%s))';

    const DEFAULT_AVATAR_FIELD = 'thumbnailphoto';

    const DEFAULT_AVATAR_DESCRIPTION_FIELD = 'displayname';


    /**
     * Allows manipulating the visitor profile properties
     * Will be called when visitor profile is initialized
     *
     * **Example:**
     *
     *     public function initProfile($visit, &$profile) {
     *         // initialize properties that will be filled based on visits or actions
     *         $profile['totalActions']         = 0;
     *         $profile['totalActionsOfMyType'] = 0;
     *     }
     *
     * @param DataTable $visits
     * @param array $profile
     */
    public function initProfile($visits, &$profile)
    {
        $username = $this->getUsername($profile);
        if ($username === false) {
            return;
        }

        list ($visitorAvatar, $visitorDescription) = $this->getData($username);
        if ($visitorAvatar !== null) {
            $profile['visitorAvatar'] = $visitorAvatar;
        }
        if ($visitorDescription != '') {
            $profile['visitorDescription'] = $visitorDescription;
        }
    }

    /**
     * @param array $visitor
     * @return string|boolean
     */
    private function getUsername($visitor)
    {
        $useMatomoUserId = $this->getSetting('usePiwikUserId') === true;
        return $useMatomoUserId ?
            $this->getUsernameByMatomoUserId($visitor):
            $this->getUsernameByCustomVariable($visitor);
    }

    /**
     * @param array $visitor
     * @return string|boolean
     */
    private function getUsernameByMatomoUserId($visitor)
    {
        if (isset($visitor['userId'])) {
            return $visitor['userId'];
        }

        /* @var $visits \Piwik\DataTable\Row[] */
        $visits = $this->getLastVisits($visitor);
        foreach ($visits as $visit) {
            $row = $visit->getColumns();
            if (!empty($row['userId'])) {
                return $row['userId'];
            }
        }

        return false;
    }

    /**
     * @param array $visitor
     * @return string|boolean
     */
    private function getUsernameByCustomVariable($visitor)
    {
        /* @var $visits \Piwik\DataTable\Row[] */
        $visits = $this->getLastVisits($visitor);

        $possibleUsernames = [];
        foreach ($visits as $visit) {
            /* @var $visit \Piwik\DataTable\Row */
            $row = $visit->getColumns();

            $customVariables = $row['customVariables'];
            if (is_array($customVariables)) {
                foreach ($customVariables as $customVariable) {
                    for ($i = 1; $i < 6; $i ++) {
                        if (isset($customVariable['customVariableName' . $i]) &&
                            $customVariable['customVariableName' . $i] == $this->getSetting('customVariableName')
                        ) {
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
     * @param string $name
     * @return mixed
     */
    private function getSetting($name)
    {
        $settings = new SystemSettings();

        $setting = $settings->getSetting($name);

        if($setting instanceof Setting){
            return $setting->getValue();
        }

        return null;
    }

    /**
     * @param array $visitor
     * @return \Piwik\DataTable\Row[] Visits
     */
    private function getLastVisits($visitor)
    {
        if (!empty($visitor['lastVisits'])) {
            /* @var $lastVisits \Piwik\DataTable */
            $lastVisits = $visitor['lastVisits'];
            $rows = $lastVisits->getRows();
            if (!empty($rows)) {
                return $rows;
            }
        }
        return !empty($visitor['visit_last']) ?
            [$visitor['visit_last']]:
            [];
    }

    private function getData($visitorUsername)
    {
        $visitorAvatar = null;
        $visitorDescription = [];

        // check if LdapConnection plugin is available!
        if (! class_exists('Piwik\Plugins\LdapConnection\API')) {
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