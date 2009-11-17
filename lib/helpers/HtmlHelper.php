<?php

class HtmlHelper{
	static function createForm($id,$object,$path=false,$classes=false){
		if(!$path)
		$path=get_bloginfo('url').'/'.get_class($object).'/create';
		HtmlHelper::form($id,$object,$path,POST,'Add new',strtolower(get_class($object)),$classes);
	}
	static function updateForm($id,$object,$path=false,$classes=false){
		if(!$path)
		$path=get_bloginfo('url').'/'.get_class($object).'/update';
		HtmlHelper::form($id,$object,$path,POST,'Save',strtolower(get_class($object)),$classes);
	}
	static function form($formid,$object,$action,$method,$submit='Send',$nonce=false,$classes=false){
		$elements=ObjectUtility::getPropertiesAndValues($object);
		$upload=$method==POST?'enctype="multipart/form-data"':'';
		$theForm="<form id='$formid' action='$action' method='$method' $upload ><table class='form-table'>";
		$theForm.=HtmlHelper::input('_redirect','hidden','referer',false,true);
		if($nonce)
		$theForm.=HtmlHelper::input('_wpnonce','hidden',wp_create_nonce($nonce),false,true);
		$validation=array();
		foreach($elements as $id => $value){
			if($id=='Id'){
				if($value>0)
				$theForm.=HtmlHelper::input($id,'hidden',$value,false,true);
			}else{
				$settings=ObjectUtility::getCommentDecoration($object,'get'.$id);
				if(array_key_exists('new',$settings))
				continue;
				$rules=array_key_exists_v('validation',$settings);
				$required='required';
				if($rules){
					if(stripos($rules,'required')===false)
					$required=false;
					$rules=str_replace('=',':',$rules);
					$rules=str_replace('|',',',$rules);
					$validation[$id]='{'.$rules.'}';
				}
				$theForm.='<tr valign=\'top\'>';
				$field=array_key_exists_v('field',$settings);
				$theForm.='<th scope=\'row\'>';
				$theForm.=HtmlHelper::label($id,$required,true);
				$theForm.='</th><td>';
				if(!$field)
					$field='text';
				if($field=='textarea'){
					$theForm.=HtmlHelper::textarea($id,$value,false,true);
				}
				else if($field=='image'){
					if(strpos($value,'http')===false)
					$theForm.=HtmlHelper::img(WP_PLUGIN_URL.$value,'',false,true);
					else
					$theForm.=HtmlHelper::img($value,'',false,true);
					$theForm.='<br />';
					if($value)
						$theForm.=HtmlHelper::input($id.'_hasimage','hidden',$value?$value:'',false,true);
					$theForm.=HtmlHelper::input($id,'file',$value,false,true);
				}
				else if($field=='dropdown'){
					$dbfield=array_key_exists_v('dbrelation',$settings);
					if($dbfield){
						$temp = new $dbfield();
						$selects=$temp->findAll();
					}
					//					$theForm.="<p>$value</p>";
					$theForm.=HtmlHelper::select($id,$selects,false,$value,true);
					if($dbfield && array_key_exists_v('addnew',$settings)=='true'){
						$theForm.=HtmlHelper::a('Add new',Communication::cleanUrl($_SERVER["REQUEST_URI"]).'?page='.strtolower($dbfield).'&action=createnew',false,true);
					}
				}
				else if($field=='url'){
					$theForm.=HtmlHelper::input($id,'text',str_replace('"','',$value),false,true);
					$theForm.='<br />'.HtmlHelper::a('Test link',$value,false,true);
				}
				else
				$theForm.=HtmlHelper::input($id,$field,stripslashes(str_replace('"','',$value)),false,true);
				$theForm.='</td></tr>';
			}
		}
		$arrays=ObjectUtility::getArrayPropertiesAndValues($object);
		foreach($arrays as $id => $value){
			$settings=ObjectUtility::getCommentDecoration($object,$id.'List');
			if(array_key_exists_v('new',$settings))
			continue;

			$rules=array_key_exists_v('validation',$settings);
			$required='required';
			if($rules){
				if(stripos($rules,'required')===false)
					$required=false;
				$rules=str_replace('=',':',$rules);
				$rules=str_replace('|',',',$rules);
				$validation[$id.'_list']='{'.$rules.'}';
			}

			$field=array_key_exists_v('field',$settings);
			$theForm.='<tr valign=\'top\'>';
			$theForm.='<th scope=\'row\'>';
			$theForm.=HtmlHelper::label($id,$required,true);
			$theForm.='</th><td>';
				
			if($field){
				if($field=='text'){
					$dbfield=array_key_exists_v('dbrelation',$settings);
					$value=array();
					if($dbfield){
						$method=$id.'List';
						$value=$object->$method(); //Repo::findAll($dbfield);
						if(!$value){
							$method=$id.'ListLazy';
							$value=$object->$method();
						}
					}
					$seperator=array_key_exists_v('seperator',$settings);
					if(!$seperator)
					$seperator=',';
					if($value)
					$list=implode($seperator,$value);
					$theForm.=HtmlHelper::input($id.'_list','text',$list,false,true);
				}else if($field=='multiple'){
					$dbfield=array_key_exists_v('dbrelation',$settings);
					if($dbfield){
						$value=Repo::findAll($dbfield);
					}
					$theForm.=HtmlHelper::select($id.'_list',$value,true,false,true);
					if($dbfield){
						$theForm.=	HtmlHelper::a('Add new',Communication::cleanUrl($_SERVER["REQUEST_URI"]).'?page='.strtolower($dbfield).'&action=createnew',false,true);
					}
				}
			}else{
				$theForm.=HtmlHelper::select($id,$value,false,false,true);
			}
			$theForm.='</td>';
		}
		$theForm.='</table>';
		$theForm.='<p class="submit">';
		if($classes)
		$theForm.=HtmlHelper::input('submit','submit',$submit,array_key_exists_v('submit',$classes),true );
		else
		$theForm.=HtmlHelper::input('submit','submit',$submit,"button-primary",true);
		$theForm.='</p></form>';
		$script='';
		if(sizeof($validation)>0){
			$rules=array();
			foreach($validation as $id => $rule)
			$rules[]=$id.':'.$rule;
			$script='<script>jQuery(document).ready(function(){jQuery("#'.$formid.'").validate({rules:{'.implode(',',$rules).'}});});</script>';
		}
		echo $theForm.$script;
	}
	static function label($id,$class=false,$dontprint=false){
		$class=$class?"class='$class' ":'';
		if($dontprint)
		return "<label for='$id' $class >$id:</label>";
		echo "<label for='$id' $class >$id:</label>";
	}
	static function input($id,$type,$value,$class=false,$dontprint=false){
		$class=$class?"class=\"$class\" ":'';
		if($dontprint)
		return "<input id=\"$id\" name=\"$id\" type=\"$type\" value=\"$value\"  $class >";
		echo  "<input id=\"$id\" name=\"$id\" type=\"$type\" value=\"$value\"  $class >";
	}
	static function textarea($id,$value,$class=false,$dontprint=false){
		$class=$class?"class='$class' ":'';
		if($dontprint)
		return "<textarea id=\"$id\" name=\"$id\" rows=\"14\" cols=\"40\" $class>$value</textarea>";
		echo "<textarea id=\"$id\" name=\"$id\" rows=\"14\" cols=\"40\" $class>$value</textarea>";
			
	}
	static function select($id,$array,$multiple=false,$selectedValues=false,$dontprint=false){
		$select="<select id=\"$id\" name=\"$id\"";
		if($multiple)
		$select.=" multiple=\"multiple\" style=\"height:70px\" size=\"5\"";
		$select.=' >';
		$select.=HtmlHelper::option(0,'None',false,true);
		if(is_array($array))
		foreach($array as $element){
			if(is_string($element) || is_int($element))
			$select.=HtmlHelper::option(str_replace('"','',$element),$element,$selectedValues==$element,true);
			else
			$select.=HtmlHelper::option($element->getId(),$element,$selectedValues==$element.'',true );
		}
		$select.='</select>';
		if($dontprint)
		return $select;
		echo $select;
	}
	static function option($value,$display,$selected=false,$dontprint=false){
		$text="<option value=\"$value\">$display</option>";
		if($selected)
		$text="<option selected=\"selected\" value=\"$value\">$display</option>";
		if($dontprint)
		return $text;
		echo $text;

	}
	static function deleteButton($text,$value,$path,$nonce){
		$theForm="<form action=\"".urldecode($path)."\" method=\"".POST."\" >";
		$theForm.=HtmlHelper::input('_redirect','hidden','referer',false,true);
		$theForm.=HtmlHelper::input('_wpnonce','hidden',wp_create_nonce($nonce),false,true);
		$theForm.=HtmlHelper::input('_method','hidden','delete',false,true);
		$theForm.=HtmlHelper::input('Id','hidden',$value,false,true);
		$theForm.=HtmlHelper::input('delete'.$value,'submit',$text,'button-secondary',true);
		$theForm.='</form>';
		echo $theForm;
	}
	static function viewLink($uri,$text,$id){
		echo "<a href=\"$uri&Id=$id\" class=\"button-secondary\" >$text</a>";
	}
	static function a($text,$path,$class=false,$dontprint=false){
		$class=$class?" class=\"$class\" ":'';
		$text=stripslashes($text);
		if($dontprint)
		return "<a href=\"$path\" $class>$text</a>";
		echo "<a href=\"$path\" $class>$text</a>";
	}
	static function img($src,$alt=false,$class=false,$dontprint=false){
		$class=$class?" class='$class'":'';
		$alt=$alt?" alt='".$alt."'":'';
		if($dontprint)
			return 	"<img $class src='$src' $alt />";
		echo "<img $class src='$src' $alt />";
	}
	static function imglink($src,$path,$alt=false,$class=false){
		$class=$class?' class=\''.$class.'\' ':'';
		$alt=$alt?" alt='".$alt."'":'';
		echo "<a href='$path' $class><img src='$src' $alt /></a>";
	}
	static function table($data,$headlines=false){
		$table='<table class="widefat post fixed">';
		$tbody.='<tbody>';
		foreach($data as $row){
			$class=strtolower(get_class($row));
			$tbody.='<tr>';
			ob_start();
			HtmlHelper::viewLink(admin_url("admin.php?page=$class&action=edit"),'Edit',$row->getId());
			$tbody.='<td style=\'width:50px;vertical-align:middle;\'>'.ob_get_contents().'</td>';
			ob_end_clean();
			if(!$headlines)
			$headlines=ObjectUtility::getProperties($row);
			foreach($headlines as $column){
				$method='get'.$column;
				$tbody.='<td>'.$row->$method().'</td>';
			}
			ob_start();
			HtmlHelper::deleteButton('Delete',$row->getId(),get_bloginfo('url').'/'.$class.'/delete',$class);
			$tbody.='<td style=\'width:50px;\'>'.ob_get_contents().'</td>';
			ob_end_clean();
			$tbody.='</tr>';
		}
		$tbody.='</tbody>';
		$ths='';
		foreach($headlines as $column){
			$ths.='<th>'.$column.'</th>';
		}
		$table.='<thead><tr><th style=\'width:50px;\'></th>'.$ths.'<th style=\'width:60px;\'></th></tr></thead>';
		$table.='<tfoot><tr><th style=\'width:50px;\'></th>'.$ths.'<th style=\'width:600px;\'></th></tr></tfoot>';
		$table.=$tbody;
		$table.='</table>';
		echo $table;
	}
	static function ActionPath($class,$type){
		echo get_bloginfo('url').'/'.strtolower($class).'/'.strtolower($type);
	}
}
?>