phpquest
========

A RestBased framework for PHP users which returns data in JSON format

SQLRest.inc.php is the base framework which needs to be extended for building your service.

Base class has alreday implemented the below methods and which can then be exposed as the endpoints for a service.

get - handles http get method
add - handle http post method
update - handle http put method
delete - handles http delete method

========================
I have also uploaded a demo service called VEHICLE_SERVICE in vehicle.php file
which can be referred for building your services along with the .htaccess file

The only functions which you may want to add will be the service endpoints which would be different from above 4 mthods 

e.g lets say I want to expose a method called vehicles which would return all  vehicles
then I have to add 'vehicles' method in my service class i.e vehicle.php

e.g lets say I want to expose a method called vehicle 
then I have to add 'vehicle' method in my service class

both of these method actually internally calls get method.

You Must Override
=================

* getDBServerArray() which returns the SQL Server information in an array (refer : vehicle.ph)
* getDBColumnDefaults() which returns the clumn names and their defaults in an array (refer : vehicle.ph)

