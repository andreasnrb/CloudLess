<?php
class Repo{
	static function findAll($class,$lazy=false){
		return Query::createFrom($class,$lazy)->execute();		
	}
	static function getById($class,$id,$lazy=false){
		Debug::Value('Repo::getById',$class);
		Debug::Value('Id=',$id);
		$objects= Query::createFrom($class,$lazy)
				  ->where(R::Eq(new $class,$id))
				  ->limit(0,1)
				  ->execute();
		return sizeof($objects)==1?$objects[0]:false;
	}
	static function find($class,$lazy=false,$restrictions=false){
		if($restrictions)
			return Query::createFrom($class,$lazy)->where($restrictions)->execute();
		else
			return self::findAll($class,$lazy);
	}
	static function findByProperty($class,$property,$value,$lazy=false){
		if(is_array($value))
			return Query::createFrom($class,$lazy)->where(R::In($property,$value))->execute();		
		return Query::createFrom($class,$lazy)->where(R::Eq($property,$value))->execute();
	}
	static function slicedFindAll($class,$firstResult,$maxResult,$order=false,$restrictions=false){
		$query=Query::createFrom($class,true)->limit($firstResult,$maxResult);
		if($order)
			$query->order($order);
		if($restrictions)
			$query->where($restrictions);
		return $query->execute();		
	}
	static function findOne($class,$requirement,$lazy=false){
		$result=array();
		if($requirement instanceof R)
			$result= Query::createFrom($class,$lazy)->where($requirement)->limit(0,1)->execute();
		else
			die('Supplied $requirement parameter is not an R(equirement) object');
		if(sizeof($result)>0)
			return $result[0];
		else
			return false;
	}
	static function total($class,$restrictions=false){
		$q=CountQuery::createFrom($class);
		if($restrictions)
			$q->where($restrictions);
		return $q->execute();
	}
}
?>