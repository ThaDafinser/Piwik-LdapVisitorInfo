# Matomo LdapVisitorInfo Plugin

## Description

Configurable Matomo plugin to view a visitor thumbnail and description live from LDAP.

***This plugin requires https://github.com/ThaDafinser/LdapConnection to work!***

## FAQ

### What does this plugin do?

It displays live a thumbnail and a description in the visitor detail page from LDAP


### How to tell Matomo which user is currently using your website?

You need to track user ID, so this plugin know which user shall be fetched from LDAP.

__Option 1. Use Matomo user ID API (recommended)__

Please see the official documentation: 
- https://matomo.org/docs/user-id/
- https://developer.matomo.org/guides/tracking-javascript-guide#user-id

Change the plugin settings to use the Matomo UserId.

Example:
```javascript
_paq.push(['setUserId', 'USER_ID_HERE']);
_paq.push(['trackPageView']);
```

__Option 2. Track a custom user visitor variable (obsoleted)__

Please see the official documentation: 
- https://matomo.org/docs/custom-variables/
- https://developer.matomo.org/api-reference/tracking-javascript

Change the plugin settings to use custom variable.

Example:
```javascript
_paq.push(["setCustomVariable", 1, "username", "<?php echo $username; ?>", "visit"]);`
```
