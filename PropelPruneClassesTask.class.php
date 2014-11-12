<?php

class PropelPruneClassesTask extends sfBaseTask {

	private $prefixes;
	private $postfixes;
	private $tableNames;
	private $excludeFileList;
	private $toDelete;

	public function configure() {
		$this->namespace = 'propel';
		$this->name      = 'prune-classes';
		$this->briefDescription = 'Removes unused model, filter and form classes';
		$this->addOptions(array(
      	new sfCommandOption('application', null, sfCommandOption::PARAMETER_REQUIRED, 'The application name', 'frontend'),
	    new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'prod'),
	    new sfCommandOption('exclude', null, sfCommandOption::PARAMETER_OPTIONAL, 'A space delimited list of files to exclude from removal', "BaseForm.class.php BaseFormPropel.class.php BaseFormFilterPropel.class.php"),
	    new sfCommandOption('schema', null, sfCommandOption::PARAMETER_OPTIONAL, 'Absolute path to target schema XML or YML file to process, leave blank to use default schema file name and location', null),
		));
	}
	
	public function execute($arguments = array(), $options = array()) {
	
		$this->toDelete = array();
		$deleteCount = 0;
		
		//Initial list of Base Propel files to not remove
		$this->excludeFileList = array("BaseForm.class.php", "BaseFormPropel.class.php", "BaseFormFilterPropel.class.php");
		
		//Append any user defined files to exclude from removal
		if (!is_null($options['exclude']))
			$this->excludeFileList = array_merge($this->excludeFileList, explode(' ', $options['exclude']));
		
		if (!is_null($options['schema']))
			$schemaXmlFile = $options['schema'];
		else
			$schemaXmlFile = sfConfig::get('sf_config_dir')."/schema.xml";
			
		$schema_type = pathinfo($schemaFile, PATHINFO_EXTENSION);

        	switch($schema_type) {
	   		case 'xml':	
    			fp = fopen($schemaFile, "r");
                if (!$fp) {
                    $this->logBlock("The file $schemaFile could not be opened.", 'ERROR_LARGE');
                    return;
                }
                $contents = fread($fp, filesize($schemaFile));
                fclose($fp);
                $xml = new SimpleXMLElement($contents);

                foreach ($xml->table as $table) {
                    $rawTableName = $table['name'];
                    if($table['phpName'])
                        $this->tableNames[] = $table['phpName'];
                    else
                        $this->tableNames[] = str_replace(' ', '', ucwords(str_replace('_', ' ', $rawTableName)));
                }

                break;
            case 'yml':
                if(!file_exists($options['schema'])) {
                    $this->logBlock("The file $schemaFile could not be opened.", 'ERROR_LARGE');
                    return;
                }
                $yaml = sfYaml::load($schemaFile);

                foreach (array_keys($yaml['propel']) as $table) {
                    $rawTableName = $table;
                    if(substr($rawTableName, 0, 1) != '_')
                    {
                        if($yaml['propel'][$rawTableName]['_attributes']['phpName'])
                            $this->tableNames[] = $yaml['propel'][$table]['_attributes']['phpName'];
                        else
                            $this->tableNames[] = str_replace(' ', '', ucwords(str_replace('_', ' ', $rawTableName)));
                    }
                }

                break;
        }
		
		//Target directories
		$libDir = sfConfig::get('sf_lib_dir');
		$modelDir = $libDir.'/model';
		$baseModelDir = $modelDir."/om";
		$mapDir = $modelDir."/map";
		$formDir = $libDir.'/form';
		$baseFormDir = $formDir.'/base';
		$filterDir = $libDir.'/filter';
		$baseFilterDir = $filterDir.'/base';
	
		$this->prefixes = array();
		$this->postfixes = array('Peer', 'Query');
		$this->buildFileRemovalList($modelDir);

		$this->postfixes = array('TableMap');
		$this->buildFileRemovalList($mapDir);
	
		$this->postfixes = array('Form.class');
		$this->buildFileRemovalList($formDir);	

		$this->postfixes = array('FormFilter.class');
		$this->buildFileRemovalList($filterDir);

		$this->prefixes = array('Base');
		$this->postfixes = array('Peer', 'Query');
		$this->buildFileRemovalList($baseModelDir);
		
		$this->postfixes = array('Form.class');
		$this->buildFileRemovalList($baseFormDir);
	
		$this->postfixes = array('FormFilter.class');
		$this->buildFileRemovalList($baseFilterDir);
	
		if (count($this->toDelete) > 0) {
			$doDelete = $this->askConfirmation("The following files are scheduled for deletion: \n\n".implode("\n", $this->toDelete)."\n\n Perform deletion? (y/n)");
			if ($doDelete) {
				foreach ($this->toDelete as $d) {
					if (unlink($d)) {
						$this->logSection('file-', $d);
						$deleteCount++;
					}
					else
						$deleteError[] = $d;
				}
				if (count($deleteError) > 0)
					$this->logBlock("The following files could not be removed: \n\n".implode("\n", $deleteError), 'ERROR_LARGE');
			}
		}
	
		if ($deleteCount == 1)
			$this->logBlock($deleteCount." file pruned", 'INFO_LARGE');
		else
			$this->logBlock($deleteCount." files pruned", 'INFO_LARGE');
	}


	private function buildFileRemovalList($dirPath) {
	
		$deleteError = array();
		$dirHandle = opendir($dirPath);
		$masterFileList = $this->tableNames;
		foreach ($this->prefixes as $prefix) {
			foreach ($masterFileList as $tableName)
				$masterFileList[] = $prefix.$tableName;
		}
		foreach ($this->postfixes as $postfix) {
			foreach ($masterFileList as $tableName)
				$masterFileList[] = $tableName.$postfix;
		}
		
		$appendPhpExt = function($filename) {
			return $filename.".php";
		};
		
		$masterFileList = array_map($appendPhpExt, $masterFileList);
		
		while (false !== ($file = readdir($dirHandle))) {
			if ($file[0] != '.' && !is_dir($dirPath."/".$file) && !in_array($file, $this->excludeFileList)) {
				if (!in_array($file, $masterFileList))
					$this->toDelete[] = $dirPath."/".$file;
			}
		}
		closedir($dirHandle);
	}

}
