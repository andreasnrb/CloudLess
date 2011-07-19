<?php
abstract class ActiveRecordBase{
	private $tempProperties=array();
	function create(){
		Debug::Message('ARB Create '.get_class($this));			
		if(!$this->runEventMethod(__FUNCTION__,'Pre'))
			return;
		$properties =ObjectUtility::getPropertiesAndValues($this);
		$vo=array();
		$vo['table']=strtolower(get_class($this));
		$vo['values']=array();
		foreach($properties as $key =>$value){
			if(isset($value)){
				if($value instanceof ActiveRecordBase){					
					if($value->getId()===false)
						$value->create();
					$vo['values'][$key]=$value->getId();										
				}else{
					$vo['values'][$key]=$value;
				}
			}
		}
		global $db;
		$id=$db->insert($vo);
		$this->setId($id);
		
		$lists=ObjectUtility::getArrayPropertiesAndValues($this);
		$voDependant=array();
		foreach($lists as $list =>$values){
			$settings=ObjectUtility::getCommentDecoration($this,$list.'List');
			$table=array_key_exists_v('dbrelationname',$settings);
			if($values)
				foreach($values as $value){
					if($table && is_subclass_of($value,'ActiveRecordBase')){
						$value->save();
						if($value->getId()){
							$col1=strtolower(get_class($value)).'_id';
							$col2=strtolower(get_class($this)).'_id';
							$row['table']=$table;
							$row['values'][$col1]=$value->getId();
							$row['values'][$col2]=$this->getId();
							$db->insert($row);
						}
						$row=array();
					}	
				}
		}
		$this->runEventMethod(__FUNCTION__,'Post');		
	}
	function delete(){
		Debug::Message('ARB Delete '.get_class($this));			
		if(!$this->runEventMethod(__FUNCTION__,'Pre'))
			return;
		$lists=ObjectUtility::getArrayPropertiesAndValues($this);
		$column=strtolower(get_class($this)).'_id';
		foreach($lists as $list =>$values){
			$settings=ObjectUtility::getCommentDecoration($this,$list.'List');
			$table=array_key_exists_v('dbrelationname',$settings);
			if($table)
				Delete::createFrom($table)->where(R::Eq($column,$this))->execute();
		}
		Delete::createFrom($this)
		->where(R::Eq($this,$this->getId()))
		->execute();
		$this->runEventMethod(__FUNCTION__,'Post');		
	}
	function update(){
		if(!$this->runEventMethod(__FUNCTION__,'Pre'))
			return;
		Debug::Message('ARB Update '.get_class($this));	
		$properties =ObjectUtility::getPropertiesAndValues($this);
		Debug::Value('Properties',$properties);
		$vo=array();
		$vo['table']=strtolower(get_class($this));
		$vo['values']=array();
		foreach($properties as $key =>$value){
			Debug::Value($key,isset($value));
			if($key!='Id' && isset($value)){
				if($value instanceof ActiveRecordBase){					
					Debug::Message($key.' instanceof ARB');
//					$value->create();
					$vo['values'][$key]=$value->getId();										
				}else{
					$vo['values'][$key]=$value;
				}
			}
		}
//		Debug::Message('<strong>Create '.$vo['table'].'</strong>');
//		Debug::Value('Values',$vo['values']);
		global $db;
		$db->update($vo,R::Eq($this,$this->getId()));
		
		$lists=ObjectUtility::getArrayPropertiesAndValues($this);
		$voDependant=array();
		$col2=strtolower(get_class($this)).'_id';
			
		foreach($lists as $list =>$values){
			$settings=ObjectUtility::getCommentDecoration($this,$list.'List');
			$table=array_key_exists_v('dbrelationname',$settings);
			$existRows=Query::create($table)->selectAll()->where(R::Eq($col2,$this->getId()))->execute();
			$newRows=array();
			Debug::Value('List values',$values);
			if(sizeof($values)>0){
				foreach($values as $value){
					if($table && is_subclass_of($value,'ActiveRecordBase')){
						Debug::Value('Update list',$table);
						$value->save();
						$col1=strtolower(get_class($value)).'_id';
						Debug::Message('Prepare relation insert');
						$insert=true;
						$totalExistRows=sizeof($existRows);
						for($x=0;$x<$totalExistRows;$x++ ){
							$existRow=$existRows[$x];
							if($existRow[$col1]==$value->getId() && $existRow[$col2]==$this->getId()){
								$insert=false;
								$newRows[]=$existRow;
							}
						}
						if($insert){
							$row['table']=$table;
							$row['values'][$col1]=$value->getId();
							$row['values'][$col2]=$this->getId();
							$newRows[]=array($value->getId(),$this->getId());
							$db->insert($row);
						}
						$row=array();
					}
				}
			}
			foreach($existRows as $existRow)
				if(!in_array($existRow,$newRows)){
					$col1=array_shift(array_keys($existRow));
					Delete::create($table)->whereAnd(R::Eq($col1,$existRow[$col1]))->where(R::Eq($col2,$existRow[$col2]))->execute();
				}
		}
		$this->runEventMethod(__FUNCTION__,'Post');		
	}
	function save(){
		Debug::Message('ARB Save '.get_class($this));			
		if(method_exists($this,'on_pre_save'))
			if(!$this->on_pre_save())
				return;
		if(!$this->runEventMethod(__FUNCTION__,'Pre'))
			return;
		if($this->getId()>0)
			$this->update();
		else
			$this->create();
		$this->runEventMethod(__FUNCTION__,'Post');
	}
	static function _($class){
		$item = new $class();
		return $item;
	}
	public function __get($property){
		$call="get".$property;
		if(method_exists($this,$call))
			return $this->$call();
		else if(strpos($property,'Lazy')!==false)
			return $this->$call();
//		else if(property_exists($this,lcfirst($property)))
//			return $this->$property;
		return $this->tempProperties[$property];
//		$trace = debug_backtrace();
//		trigger_error('Undefined property via __get(): ' . $property .' in ' . $trace[0]['file'] .' on line ' . $trace[0]['line'],E_USER_NOTICE);
	}
	public function __set($property,$value){
		$call="set".$property;
		if(method_exists($this,$call))
			return $this->$call($value);
		$this->tempProperties[$property]=$value;
//		$trace = debug_backtrace();
//		trigger_error('Undefined property via __set(): ' . $property .' in ' . $trace[0]['file'] .' on line ' . $trace[0]['line'],E_USER_NOTICE);        
	}
	
    public function __isset($property) {
        return !empty($this->$property);
    }
    public function __unset($property) {
    	$property=strtolower($property);
        unset($this->$property);
    }/**/
	
	
	public function __call($method,$arguments){
		if($this->getId()){
			Debug::Message('ARB __call '.get_class($this).'->'.$method);
			Debug::Value('Arguments',$arguments);	
			if(empty($arguments)){
				$method=str_replace('Lazy','',$method);
				$settings=ObjectUtility::getCommentDecoration($this,$method);
				$foreign=$settings['dbrelation'];
				$temp=new $foreign();
				$foreign=strtolower($foreign);
				if(strpos($method,'List')!==false){
					$method=str_replace('get','',$method);									
					$settings=ObjectUtility::getCommentDecoration($this,$method);
//					$stmt=new SelectStatement();
					$table=strtolower(get_class($this));
/*					$stmt->From($foreigntable);
					$stmt->From($this);*/
//					$properties =ObjectUtility::getProperties($temp);
					
/*					foreach($properties as $property)
						$stmt->Select($foreigntable.'.'.strtolower($property));
						$stmt->From($settings['dbrelationname']);
						$stmt->Where(R::Eq($this,$table.'_id'));*/
//						$stmt->Where(R::Eq());
//						$db->select($select);
// select * from item, company,relation WHERE item.id = relation.item_id AND company.id=relation.company_id
					Debug::Value('Relationname',$settings['dbrelationname']);

					$q=Query::createFrom($temp);
					$q->from($settings['dbrelationname']);
					$q->whereAnd(R::Eq($temp,$settings['dbrelationname'].'.'.$foreign.'_id',true));
					$q->where(R::Eq($table.'_id',$this));
					$list=$q->execute();
					$method='add'.str_replace('List','',$method);
					foreach($list as $li){
						$this->$method($li);
					}
					return $list;
					
				}else{
					$temp= $temp->getById($this->$method());
					$method=str_replace('get','set',$method);
					$this->$method($temp);
					return $temp;
				}
			}
		}
	}
	private function runEventMethod($event,$when){
		$method='on'.$when.ucfirst($event);
		$class=get_class($this);
		HookHelper::run($method,$this);		
		HookHelper::run($class.'->'.$method,$this);
		if(method_exists($this,$method))
			return $this->$method();
		return true;
	}
}