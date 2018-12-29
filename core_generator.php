<?php
header('Content-Type: text/html; charset=utf-8');

const TEMPLATE_ROOT = __DIR__ . "/core_generator_template";
const TEMPLATE_MODEL = TEMPLATE_ROOT . "/model";
const TEMPLATE_SERVICE = TEMPLATE_ROOT . "/service";
const CORE_ROOT = __DIR__ . "/core";
const CORE_MODEL = CORE_ROOT . "/model";
const CORE_SERVICE = CORE_ROOT . "/service";

$action = filter_input(INPUT_POST, "action", FILTER_SANITIZE_STRING);
$log = [];
if ($action == "loadAllTables") {
    $dbHost = filter_input(INPUT_POST, "db_host");
    $dbLogin = filter_input(INPUT_POST, "db_login");
    $dbPwd = filter_input(INPUT_POST, "db_password");
    $dbName = filter_input(INPUT_POST, "db_dbname");
    $dbPort = filter_input(INPUT_POST, "db_port");

    $dbConnection = mysqli_connect($dbHost, $dbLogin, $dbPwd, $dbName, $dbPort);

    if ($dbConnection === FALSE)
        die("Nepovedlo se spojit s databází, chyba " . mysqli_connect_error());
    $allTables = getAllTables($dbConnection);
    $tableColumns = [];
    foreach ($allTables as $table) {
        $tableColumns[$table] = getAllColumns($dbConnection, $table);
    }
} elseif ($action == "generate") {
    $projectName = filter_input(INPUT_POST, "projectName");
    $allTables = explode(",", filter_input(INPUT_POST, "allTables"));

    
    
    generateFolderStructure();
    
    //static files replaces
    foreach (["CreatedByObject.class.php", "IdObject.class.php", "DbField.class.php"] as $filename) {
        $file = CORE_MODEL . "/$filename";
        logg("Performing auto-replacing on $file");
        $contents = file_get_contents($file);
        $contents = str_replace("CG_TMP_PROJECTNAME", $projectName, $contents);
        $contents = str_replace("CG_TMP_DATE", (new DateTime())->format("j.n.Y H:i:s"), $contents);
        logg("Saving replaced file $file");
        file_put_contents($file, $contents);
    }
    
    foreach ($allTables as $table) {
        generateTable($projectName, $table);
    }
    
    unlink(CORE_MODEL . "/CG_TMP_Object.class.php");
    unlink(CORE_SERVICE . "/CG_TMP_Service.class.php");
}

function generateTable($projectName, $tableName) {
    $processTable = filter_input(INPUT_POST, "processTable$tableName");
    if (!$processTable) {
        logg("Skipping table $tableName");
        return FALSE;
    }
    logg("Generating table $tableName");
    $modelName = filter_input(INPUT_POST, "modelName$tableName");
    logg("Table:$tableName model name is $modelName");
    $modelExtends = filter_input(INPUT_POST, "modelExtends$tableName");
    logg("Table:$tableName model extends $modelExtends");
    $serviceName = filter_input(INPUT_POST, "serviceName$tableName");
    logg("Table:$tableName service name is $serviceName");
    $allColumnNames = explode(",", filter_input(INPUT_POST, "allColumns$tableName"));
    $cols = [];
    $idField = NULL;
    foreach ($allColumnNames as $column) {
        $col = getColumn($tableName, $column);
        $cols[] = $col;
        if(!$col["editable"])
            $idField = $col["name"];
    }
    if($idField == NULL){
        logg("Table:$tableName column with primary key does not exist!");
    }
    generateModel($projectName, $modelName, $modelExtends, $cols);
    generateService($projectName, $serviceName, $modelName, $tableName, $idField, $cols);
}

function generateService($projectName, $serviceName, $objectName, $tableName, $idField, $columns) {
    logg("Generating service $serviceName");
    $serviceTmpFile = CORE_SERVICE . "/CG_TMP_Service.class.php";
    $serviceFile = CORE_SERVICE . "/$serviceName.class.php";

    if($idField == NULL){
        logg("Service $serviceName cannot be created without primary key");
        return FALSE; // cannot create service for tables without primary key
    } 
    
    if (!copy($serviceTmpFile, $serviceFile)) {
        logg("Failed to copy file $serviceTmpFile to $serviceFile");
    }
    
    //replacing starts
    logg("Performing auto-replacing on $serviceFile");
    $contents = file_get_contents($serviceFile);
    $contents = str_replace("CG_TMP_PROJECTNAME", $projectName, $contents);
    $contents = str_replace("CG_TMP_Service", $serviceName, $contents);
    $contents = str_replace("CG_TMP_Object", $objectName, $contents);
    $contents = str_replace("CG_TMP_DATE", (new DateTime())->format("j.n.Y H:i:s"), $contents);
    $contents = str_replace("CG_TMP_Table", $tableName, $contents);
    $contents = str_replace("CG_TMP_ID_FIELD", $idField, $contents);
    
    $replacesDbFieldsEditable = [];
    $replacesDbFieldsUneditable = [];
    
    foreach ($columns as $col) {
        if($col["editable"]){
            $replacesDbFieldsEditable[] = ["CG_TMP_PROPERTY" => $col["prop"],"CG_TMP_FIELD" => $col["name"]];
        } else {
            $replacesDbFieldsUneditable[] = ["CG_TMP_PROPERTY" => $col["prop"],"CG_TMP_FIELD" => $col["name"]];
        }
    }
    $contents = repeatBlock($contents, "/*CG_TMP_DBFIELD_PRIMARY:START*/", "/*CG_TMP_DBFIELD_PRIMARY:END*/", $replacesDbFieldsUneditable);
    $contents = repeatBlock($contents, "/*CG_TMP_DBFIELD_NORMAL:START*/", "/*CG_TMP_DBFIELD_NORMAL:END*/", $replacesDbFieldsEditable);
    
    file_put_contents($serviceFile, $contents);
    logg("Saving replaced file $serviceFile");
}


function generateModel($projectName, $modelName, $modelExtends, $columns) {
    logg("Generating model $modelName");
    $modelTmpFile = CORE_MODEL . "/CG_TMP_Object.class.php";
    $modelFile = CORE_MODEL . "/$modelName.class.php";

    if (!copy($modelTmpFile, $modelFile)) {
        logg("Failed to copy file $modelTmpFile to $modelFile");
    }
    
    
    //replacing starts
    logg("Performing auto-replacing on $modelFile");
    $contents = file_get_contents($modelFile);
    $contents = str_replace("CG_TMP_PROJECTNAME", $projectName, $contents);
    $contents = str_replace("CG_TMP_Object", $modelName, $contents);
    $contents = str_replace("CG_TMP_DATE", (new DateTime())->format("j.n.Y H:i:s"), $contents);
    $contents = str_replace("CG_TMP_EXTENDS_OBJECT", $modelExtends, $contents);
    $replacesProps = [];
    $replacesGetters = [];
    $replacesSetters = [];
    foreach ($columns as $col) {
        $replacesProps[] = ["CG_TMP_FIELD_TYPE" => $col["propType"],"CG_TMP_FIELD_NAME" => $col["prop"]];
        $replacesGetters[] = ["CG_TMP_FIELD_UC" => camelizeUc($col["prop"]),"CG_TMP_FIELD_NAME" => $col["prop"]];
        $replacesSetters[] = ["CG_TMP_FIELD_UC" => camelizeUc($col["prop"]),"CG_TMP_FIELD_NAME" => $col["prop"],"CG_TMP_FIELD_TYPE" => $col["propType"],"CG_TMP_FIELD" => camelize($col["prop"])];
    }
    $contents = repeatBlock($contents, "/*CG_TMP_FIELD:START*/", "/*CG_TMP_FIELD:END*/", $replacesProps);
    $contents = repeatBlock($contents, "/*CG_TMP_GETTER:START*/", "/*CG_TMP_GETTER:END*/", $replacesGetters);
    $contents = repeatBlock($contents, "/*CG_TMP_SETTER:START*/", "/*CG_TMP_SETTER:END*/", $replacesSetters);
    file_put_contents($modelFile, $contents);
    logg("Saving replaced file $modelFile");
}

function repeatBlock($contents, $starter, $finisher, $replaces){
    $startPos = mb_strpos($contents, $starter);
    $endPos = mb_strpos($contents, $finisher) + mb_strlen($finisher);
    $blockLength = $endPos - $startPos;
    $blockTmp = mb_substr($contents, $startPos, $blockLength);
    $newBlocks = [];
    foreach ($replaces as $replaceBlock) {
        $newBlock = $blockTmp;
        foreach ($replaceBlock as $key => $value) {
            $newBlock = str_replace($key, $value, $newBlock);
        }
        $newBlocks[] = $newBlock;
    }
    $contents = str_replace($blockTmp, join("", $newBlocks), $contents);
    $contents = str_replace($starter, "", $contents);
    $contents = str_replace($finisher, "", $contents);
    return $contents;
}

function generateFolderStructure() {
    logg("Copying template folder " . TEMPLATE_ROOT . " to " . CORE_ROOT);
    if (file_exists(CORE_ROOT) && is_dir(CORE_ROOT)){
        logg("Deleting existing folder " . CORE_ROOT);
        rrmdir(CORE_ROOT);
    }
    return recurse_copy(TEMPLATE_ROOT, CORE_ROOT);
}

function rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . "/" . $object))
                    rrmdir($dir . "/" . $object);
                else {
                    unlink($dir . "/" . $object);
                }
            }
        }
        rmdir($dir);
    }
}

function recurse_copy($source, $dest) {
    // Check for symlinks
    if (is_link($source)) {
        return symlink(readlink($source), $dest);
    }
    // Simple copy for a file
    if (is_file($source)) {
        return copy($source, $dest);
    }
    // Make destination directory
    if (!is_dir($dest)) {
        mkdir($dest);
    }
    // Loop through the folder
    $dir = dir($source);
    while (false !== ($entry = $dir->read())) {
        // Skip pointers
        if ($entry == '.' || $entry == '..') {
            continue;
        }
        // Deep copy directories
        recurse_copy("{$source}/{$entry}", "{$dest}/{$entry}");
    }
    // Clean up
    $dir->close();
    return true;
}

function getColumn($table, $column) {
    logg("Table:$table loading column $column");
    $processColumn = filter_input(INPUT_POST, "processField$table:$column");
    if (!$processColumn) {
        logg("Skipping column $table.$column");
        return FALSE;
    }
    $propName = filter_input(INPUT_POST, "nameField$table:$column");
    logg("Field:$table.$column property name is $propName");
    $propType = filter_input(INPUT_POST, "typeField$table:$column");
    logg("Field:$table.$column property type is $propType");
    $editable = filter_input(INPUT_POST, "editableField$table:$column") ? TRUE : FALSE;
    logg("Field:$table.$column editable is " . ($editable ? "true" : "false"));
    return ["name" => $column, "prop" => $propName, "propType" => $propType, "editable" => $editable];
}

function getAllTables(mysqli $dbConnection) {
    $ps = $dbConnection->prepare("SHOW TABLES");
    $ps->execute();
    $tables = [];
    foreach ($ps->get_result()->fetch_all() as $row) {
        $tables[] = $row[0];
    }

    return $tables;
}

function getAllColumns(mysqli $dbConnection, $table) {
    $ps = $dbConnection->prepare("SHOW COLUMNS FROM $table");
    $ps->execute();
    $result = $ps->get_result();
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row;
    }

    return $columns;
}

function guessPropertyType($dbType) {
    if (strpos($dbType, "int") === 0)
        return "integer";
    if (strpos($dbType, "varchar") === 0)
        return "string";
    if (strpos($dbType, "text") === 0)
        return "string";
    if (strpos($dbType, "set") === 0)
        return "string";
    if (strpos($dbType, "decimal") === 0)
        return "double";
    if (strpos($dbType, "date") === 0)
        return "DateTime";
    if (strpos($dbType, "datetime") === 0)
        return "DateTime";
    if (strpos($dbType, "timestamp") === 0)
        return "DateTime";
    if (strpos($dbType, "time") === 0)
        return "DateTime";
    if (strpos($dbType, "enum") === 0)
        return "array";
    return "";
}

function guessEditable($key) {
    return $key === "PRI";
}

function camelizeUc($text) {
    return ucfirst(camelize($text));
}

function camelize($text) {
    $text = str_replace(" ", "_", trim($text));
    $text = str_replace(".", "_", $text);
    $text = str_replace(":", "_", $text);
    $parts = explode("_", $text);
    foreach ($parts as &$part) {
        $part = ucfirst($part);
    }
    return lcfirst(join("", $parts));
}

function logg($text) {
    global $log;
    $log[] = (new \DateTime())->format("j.n.Y H:i:s") . " " . $text;
}
?>

<html>
    <head>
        <meta charset="utf-8">
        <meta name="author" content="Matej Kminek">
        <title>Core Generator</title>
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/css/bootstrap.min.css" integrity="sha384-GJzZqFGwb1QTTN6wy59ffF1BuGJpLSa9DkKMp0DgiMDm4iYMj70gZWKYbI706tWS" crossorigin="anonymous">
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.3/css/all.css" integrity="sha384-UHRtZLI+pbxtHCWp1t77Bi1L4ZtiqrqD80Kn4Z8NTSRyMA2Fd33n5dQ8lWUE00s/" crossorigin="anonymous">

        <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.6/umd/popper.min.js" integrity="sha384-wHAiFfRlMFy6i5SRaxvfOCifBUQy1xHdJ/yoi7FRNXMRBu5WHdZYu1hA6ZOblgut" crossorigin="anonymous"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/js/bootstrap.min.js" integrity="sha384-B0UglyR+jN6CkvvICOB2joaf5I4l3gm9GU6Hc1og6Ls7i6U/mkkaduKaBhlAXv9k" crossorigin="anonymous"></script>

    </head>
    <body>
        <h4>CORE GENERATOR</h4>
        <div class="container">
            <?php 
            if (!empty($log)) { 
                echo "<div class='alert alert-secondary'>". join("<br>", $log) . "</div>";
            } ?>
            
            <form method="POST">
                <h5>Připojení k MYSQL:</h5>
                <input type="hidden" name="action" value="loadAllTables">
                <table class="table table-bordered">
                    <tr><th>HOST:</th><td><input class="form-control" type="text" name="db_host" value="127.0.0.1" /></td></tr>
                    <tr><th>LOGIN:</th><td><input class="form-control" type="text" name="db_login" /></td></tr>
                    <tr><th>HESLO:</th><td><input class="form-control" type="text" name="db_password" /></td></tr>
                    <tr><th>DATABÁZE:</th><td><input class="form-control" type="text" name="db_dbname" /></td></tr>
                    <tr><th>PORT:</th><td><input class="form-control" type="text" name="db_port" value="3306" /></td></tr>
                </table>
                <button class="btn btn-outline-primary">Načíst všechny tabulky z databáze</button>
            </form>

            <?php if (!empty($allTables)) { ?>
                <form method="POST">
                    <input type="hidden" name="action" value="generate">
                    <input type="hidden" name="allTables" value="<?php echo join(",", $allTables); ?>">
                    <p>
                        <strong>Název projektu</strong>
                        <input type='text' class='form-control form-control-lg' name='projectName' value='' required>
                    </p>
                    <div class="accordion" id="accordionTables">
                        <?php
                        $first = TRUE;
                        foreach ($allTables as $table) {
                            echo '<div class="card">
                        <div class="card-header" id="heading' . $table . '">
                            <h2 class="mb-0">
                            <input type="checkbox" checked class="tableCheck" name="processTable' . $table . '" title="Check to process" />
                                <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapse' . $table . '" aria-expanded="true" aria-controls="collapse' . $table . '">
                                    ' . $table . '
                                </button>
                            </h2>
                        </div>

                        <div id="collapse' . $table . '" class="collapse ' . ($first ? "show" : "") . '" aria-labelledby="heading' . $table . '" data-parent="#accordionTables">
                            <div class="card-body">
                            
                            <p><strong>Název třídy service</strong><input type="text" class="form-control" name="serviceName' . $table . '" value="' . camelizeUc($table) . 'Service" /></p>
                            <p><strong>Název třídy modelu</strong><input type="text" class="form-control" name="modelName' . $table . '" value="' . camelizeUc($table) . '" /></p>
                            <p><strong>Model extenduje</strong><select class="form-control w-25" name="modelExtends' . $table . '"><option value="IDObject" selected>IDObject</option><option value="CreatedByObject">CreatedByObject</option></select></p>
                            <table class="table table-bordered table-hover">
                                <tr>
                                <th class="table-primary"></th>
                                <th class="table-primary">Pole</th>
                                <th class="table-primary">Typ</th>
                                <th class="table-primary">Nulové</th>
                                <th class="table-primary">Klíč</th>
                                <th class="table-primary">Výchozí</th>
                                <th class="table-primary">Extra</th>
                                
                                <th class="table-warning">Název</th>
                                <th class="table-warning">Typ</th>
                                <th class="table-warning">Edit.</th>
                                </tr>';
                            $allFieldNames = [];
                            foreach ($tableColumns[$table] as $col) {
                                $allFieldNames[] = $col["Field"];
                                echo "<tr>"
                                . "<td><input type='checkbox' checked name='processField" . $table . ":" . $col["Field"] . "' /></td>"
                                . "<td>" . $col["Field"] . "</td>"
                                . "<td>" . $col["Type"] . "</td>"
                                . "<td>" . $col["Null"] . "</td>"
                                . "<td>" . $col["Key"] . "</td>"
                                . "<td>" . $col["Default"] . "</td>"
                                . "<td>" . $col["Extra"] . "</td>"
                                . "<td><input type='text' class='form-control' name='nameField" . $table . ":" . $col["Field"] . "' value='" . camelize($col["Field"]) . "'></td>"
                                . "<td><input type='text' class='form-control' name='typeField" . $table . ":" . $col["Field"] . "' value='" . guessPropertyType($col["Type"]) . "'></td>"
                                . "<td><input type='checkbox' name='editableField" . $table . ":" . $col["Field"] . "' " . (guessEditable($col["Key"]) ? "" : "checked") . "></td>"
                                . "</tr>";
                            }

                            echo '</table>
                                <input type="hidden" name="allColumns' . $table . '" value="' . join(",", $allFieldNames) . '">
                            </div>
                        </div>
                    </div>';
                            $first = FALSE;
                        }
                        ?>


                    </div>
                    <button class="btn btn-outline-dark">Vygenerovat PHP jádro</button>
                </form>
            <?php } ?>

        </div>

    </body>
</html>
