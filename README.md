# Piwik LdapVisitorInfo Plugin

## Description

Configurable piwik plugin to view a visitor thumbnail and description live from LDAP.

***This plugin requires https://github.com/ThaDafinser/LdapConnection to work!***

## FAQ

__Why is PIWIK 2.5 required?__

Because the configuration (to be explicit accountFilterFormat) is destroyed in the previous version
See the ticket here: https://github.com/piwik/piwik/issues/5890


__What does this plugin do?__

It displays live a thumbnail and a description in the visitor detail page from LDAP


__How to tell Piwik which user is currently using your website?__

You need so track a custom user (username, mail, ...) visitor variable, so this plugin know which user shall be fetched from LDAP.
Please see the official documentation: http://piwik.org/docs/custom-variables/ or http://developer.piwik.org/api-reference/tracking-javascript#custom-variables

Example:
`_paq.push(["setCustomVariable", 1, "username", "<?php echo $usenamer; ?>", "visit"]);`
