# Rate Limiter

#### CONTENTS OF THIS FILE
---------------------
* Introduction
* Features
* Requirements
* Installation
* Configuration
* Assumptions
* Caution
* Future Improvements
* Maintainer(s)
* Change Log

##### Introduction
------------------
*Rate Limiter* module can be useful when to disallow concurrent web service 
access to the application. 

##### Features
This module exposes a "Rate Limiting" service for RestAPI web service calls.
This service features:
* Define number of concurrent allowed hits in a given time window.
* Rate limiting on each request.
* Limit hits based on IP address.
* IP White listing option.
* Defines a separate cache bin to store the rate limiter hits and counts.

##### Requirements
------------------
* RESTful Web Services
* Serialization

##### Installation
------------------
Follow [installation](https://www.drupal.org/documentation/install/modules-themes/modules-8) 
guide to install the module into the site.

##### Configuration
------------------
Navigate to Configuration >> Web services >> Rate Limiter Configuration 
(admin/config/services/rate-limiter).
The configuration has two segments.
* General Configuration
* Access Rules

**General Configuration** has basic configurations to enable the module with 
allowed request limit in an allowed time frame. 
An optional message can be shown when the limit is reached.

**Access Rules** has two option to enable rate limiting service for all 
web-service request or based on IP. 
If IP based rate limiting is selected then there is an IP based 
white listing option available.

##### Assumptions
------------------
The following points were assumed while developing the module.

1. Every Web-service call will have "Accept" header mentioned.
2. The Rate Limiting service will only work on requests with 
"application/json", "application/xml" and "application/hal+json" accept headers.
3. Command line requests and "text/html" requests are considered as normal
drupal requests to the site not as Service calls.

##### Caution
------------------
This module stores all it's rate limiter hit counts in Drupal's cache. 
So clearing cache will remove all the items. 
Assumed that in a production environment caches are not cleared more often.

##### Future Improvements
------------------
* Enable Bearer Token based Rate limiting service with [OAuth2 Server](https://www.drupal.org/project/oauth2_server) module.
* Based on Feature Requests more improvements.
* Integration test suite for testing Rate Limiting service.

##### Maintainer(s)
------------------
###### Current maintainer
* Aneek Mukhopadhyay (aneek) - https://www.drupal.org/u/aneek
 
##### Change Log
------------------
* 8.0-1.0 - Initial version created.
