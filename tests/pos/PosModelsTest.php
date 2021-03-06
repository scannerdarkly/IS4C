<?php

use COREPOS\pos\lib\Database;

/**
 * @backupGlobals disabled
 */
class PosModelsTest extends PHPUnit_Framework_TestCase
{
    /**
     * Name collisions w/ Office model classes screw up
     * auto discovery via AutoLoader::listModules
     *
     * Manually add full namespaced classes by file
     */
    private function addByFile($models, $path, $ns)
    {
        $dir = opendir($path);
        while (($file=readdir($dir)) !== false) {
            if ($file[0] == '.' || substr($file, -4) != '.php') {
                continue;
            }
            $class = $ns . substr($file, 0, strlen($file)-4);
            if (!in_array($class, $models)) {
                $models[] = $class;
            }
        }

        return $models;
    }

    public function testModels()
    {
        $models = AutoLoader::listModules('COREPOS\pos\lib\models\BasicModel');
        $models = $this->addByFile($models, __DIR__ . '/../../pos/is4c-nf/lib/models/op', 'COREPOS\\pos\\lib\\models\\op\\');
        $models = $this->addByFile($models, __DIR__ . '/../../pos/is4c-nf/lib/models/trans', 'COREPOS\\pos\\lib\\models\\trans\\');
        $dbc =  Database::pDataConnect();
        foreach ($models as $class) {
            $obj = new $class($dbc);
            if (strstr($class, 'EWic')) continue;
            // this just improves coverage; the doc method isn't
            // user-facing functionality
            $this->assertInternalType('string', $obj->doc());
            if (substr($class, 0, 26) == 'COREPOS\\pos\\lib\\models\\op\\') {
                $dbname = CoreLocal::get('pDatabase');
                $dbc =  Database::pDataConnect();
                $obj->setConnection($dbc);
            } elseif (substr($class, 0, 29) == 'COREPOS\\pos\\lib\\models\\trans\\') {
                $dbname = CoreLocal::get('tDatabase');
                $dbc =  Database::tDataConnect();
                $obj->setConnection($dbc);
            } else {
                continue;
            }
            ob_start();
            $obj->whichDB($dbname);
            $n = $obj->normalize($dbname);
            $out = ob_get_clean();
            $this->assertEquals(0, $n, "$class is not normalized: $out");
        }
    }

    /**
      Create, update, and delete an employee record
      to cover BasicModel functionality
    */
    public function testBasics()
    {
        $obj = new COREPOS\pos\lib\models\op\EmployeesModel(Database::pDataConnect());
        $obj->emp_no(99);
        $obj->FirstName('test');
        $obj->save();
        $this->assertEquals(true, $obj->load());
        $obj->FirstName('testchange');
        $this->assertEquals(true, $obj->save());
        $this->assertEquals(true, $obj->delete());
    }

    public function testTendersModel()
    {
        $obj = new COREPOS\pos\lib\models\op\TendersModel(Database::pDataConnect());
        $this->assertInternalType('array', $obj->getMap());
        $obj->hookAddColumnTenderModule();
    }

    public function testParametersModel()
    {
        $obj = new COREPOS\pos\lib\models\op\ParametersModel(Database::pDataConnect());
        $obj->is_array(0);
        $obj->param_value('true');
        $this->assertEquals(true, $obj->materializeValue());
        $obj->param_value('false');
        $this->assertEquals(false, $obj->materializeValue());
        $obj->is_array(1);
        $obj->param_value('');
        $this->assertEquals(array(), $obj->materializeValue());
        $obj->param_value('1');
        $this->assertEquals(array(1), $obj->materializeValue());
        $obj->param_value('1,2');
        $this->assertEquals(array(1,2), $obj->materializeValue());
        $obj->param_value('one=>1');
        $this->assertEquals(array('one'=>1), $obj->materializeValue());
        $obj->param_value('one=>1,two=>2');
        $this->assertEquals(array('one'=>1,'two'=>2), $obj->materializeValue());
    }
}

