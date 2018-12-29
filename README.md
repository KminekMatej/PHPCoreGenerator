# PHPCoreGenerator
Tool I use to generate PHP CORE classes based on Mysql database scanning. Useful for anyone doing all the same models and services based on mysql table again and again

## How to use
1) Deploy the whole folder to your web server (can be also run locally, but usually mysql port is open only for localhost connections)
2) Open core_generator.php file - you will see the GUI
3) Enter database details and parse data from database, using the blue submit button
4) After submitting you will see the box for each table found in database. All tables are checked for generating as default. Uncheck tables where you do not want to generate model and service file
5) In each table you will see list of fields as loaded in database (blue columns) and expected property name and type - you can change that as you wish
6) You can also uncheck field you do not want to add into model/service
7) Specify project name In the top input box (used for namespacing generated core)
8) On each table, edit Model name, Service name and class that Model extends. (either IdObject or CreatedBy object). ID Object already contains two fields - ID and Created. CreatedByObject extends IdObject and adds also CreatedBy field.
9) Click on the dark button in the end to start the generating proccess

## Generating proccess
Created folders 
./core
./core/model
./core/service

Model file is a class that contains all the properties specified for each DB column with corresponding type and name. In model folder already exists model DbField, IdObject and CreatedyObject. IdObject and CreatedByObject are parent classes for each generated model class. DbField is a helpful class for determining bind between database columnd and object property.

Service file already contains file TableService, which is a field that operates with database and contains basic mysql functions create, find($id) -> return Model, findAll() -> return Model[], delete($id) and update(Model $model). Service is than taking care of correctly bind between php objects and database columns.

### Usage:
$model = $this->modelService->find(25);
$model->setUpdated(new DateTime());
$this->modelService->update($model);
