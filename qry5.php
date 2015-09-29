<?php

class QRYset {

	protected $mix;	/* Literal string to mix with the rest of query */
	protected $glue;	/* Literal string to mix elements in set */

	protected $elems = array();	/* Array of elements */

	protected $paren = false; /* Wrap in parentheses */

	public $priority = 10;	/* Order in ->script queue */

	public function add($elem) {
		$this->elems[] = $elem;
	}
	public function rem($remove) {
		foreach ($this->elems as $key=>&$elem) {
			if ($elem === $remove) {
				//unset($this->elems[$key]); unset deletes the value object, so we can't use it
				array_splice($this->elems, $key, 1);
				break;
			}
		}
	}
	public function count() { return count($this->elems); }

	public function setParen($_paren) {
		$this->paren = $_paren;
	}
	public function setMix($_mix) {
		$this->mix = $_mix;
	}
	public function setGlue($_glue) {
		$this->glue = $_glue;
	}

	public function __toString() {
		return $this->toString();
	}
	public function asHint() {
		return print_r($this, 1);
	}
	public function toArray() {
		return $this->elems;	
	}
	public function toString($flags=0) {
		$ret = $this->mix;
		$glue = '';
		foreach ($this->elems as $elem) {
			$ret .= $glue;
			$ret .= $elem->toString($flags);
			$glue = $this->glue;
		}
		if ($this->paren) return '('.$ret.')';
		return $ret;
	}
	public function visDump($flags=0) {
		$ret = $this->mix;
		$glue = '';
		$class = substr(get_class($this), 3);
		$class = substr($class, 0, strlen($class) - 3);
		foreach ($this->elems as $elem) {
			$ret .= $glue;
			$ret .= "<span class='$class' title='".htmlentities($elem->asHint())."'>";
			$ret .= $elem->visDump($flags);
			$ret .= "</span>";
			$glue = $this->glue;
		}
		if ($this->paren) return '('.$ret.')';
		return $ret;
	}

	public function first() {		return $this->findByOffset(0);	}
	public function last() {		return $this->findByOffset(-1);	}

	public function findBy($property, $value) {
		foreach ($this->elems as $elem) {
			$func = 'as'.$property;
			if (//isset($elem->{$property}) 
//				&& 
//				$elem->{$property} == $value) return $elem;
				$elem->$func() == $value) return $elem;
		}
		return NULL;
	}
	public function findByOffset($offset) {
		if ($offset < 0) $offset = count($this->elems) + $offset;
		if ($offset < 0) throw new Exception('Attempting to get by offset '.$offset);
		if ($offset > count($this->elems) - 1) throw new Exception('Attempting to get by offset '.$offset. ', only have '.count($this->elems) .' elements');

		if (!is_numeric(key($this->elems))) /* hack for datasets :/ */
			return current(array_slice($this->elems, $offset, 1));
		return $this->elems[$offset];
	}
}

class QRYdataset extends QRYset {

	public function add($value, $key = null) {
		if ($key === null) $key = sizeof($this->elems);//return parent::add($value);
		$this->elems[$key] = $value;		
	}

	public function reset_key($offset, $key) {
		$this->elems[$key] = $this->elems[$offset];
		unset($this->elems[$offset]);//note: might need to change to array_splice, grep for it
	}
	public function keyByOffset($offset) {
		//todo:error check? (or actually, rewrite everything)
		if (1==1) {
		
		}
		$keys = array_keys($this->elems);
		return $keys[$offset];
	}
	public function findByKey($key) {
		if (isset($this->elems[$key])) return $this->elems[$key];
		return null;
	}

	public function visDump($flags=0, $i='?') {
		return "DATASET ".$i.": ".print_r($this->elems, 1);
	}
}

class QRYtableset extends QRYset {
	protected $mix = 'FROM ';
	protected $glue = ', ';
	
	public $priority = 20;
}

class QRYfieldset extends QRYset {
	protected $mix = 'SELECT ';
	protected $glue = ', ';
	
	public $priority = 0;	
}
class QRYorderset extends QRYset {
	protected $mix = 'ORDER BY ';
	protected $glue = ', ';

	public $priority = 90;	

	public function visDump($flags=0) {
		return parent::visDump($flags & ~QRY::ALIAS_FIELDS);	
	}
	public function toString($flags=0) {
		return parent::toString($flags & ~QRY::ALIAS_FIELDS);	
	}
}

class QRYvalueset extends QRYmatchset {
	protected $mix = 'SET ';
	protected $glue = ', ';	

	public $priority = 30;

	public $split_mode = false;

	public function toString($flags=0) {
		if (!$this->split_mode) return parent::toString($flags); 
		$ret  = '';
		$ret2 = '';
		$glue = '';
		foreach ($this->elems as $elem) {
			$ret .= $glue;
			$ret .= $elem->toString($flags, 'left');
			$ret2 .= $glue;
			$ret2 .= $elem->toString($flags, 'right');
			$glue = $this->glue;
		}
		if ($this->paren) {
			$ret = '('.$ret.')';
			$ret2 = '('.$ret2.')';
		}
		return $ret . $this->mix . $ret2;
	}
	public function visDump($flags=0) {
		if (!$this->split_mode) return parent::visDump($flags);
		$ret = '';
		$ret2 = '';
		$glue = '';
		foreach ($this->elems as $elem) {
			$ret .= $glue;
			$ret .= $elem->visDump($flags, 'left');
			$ret2 .= $glue;
			$ret2 .= $elem->visDump($flags, 'right');
			$glue = $this->glue;
		}
		if ($this->paren) {
			$ret = '('.$ret.')';
			$ret2 = '('.$ret2.')';
		}
		return $ret . $this->mix . $ret2;
	}
}

class QRYmatchset extends QRYset {
	protected $mix = 'WHERE ';
	protected $glue = ' AND ';	

	public $priority = 50;

	public function toString($flags=0) {
		$glue = '';
		$ret = $this->mix;
		foreach ($this->elems as $elem) {
			$ret .= $glue;
			$ret .= $elem->toString($flags);
			$glue = $this->glue;
		}
		if ($this->paren) $ret = '('.$ret.')';
		return $ret;
	}
	public function visDump($flags=0) {
		$glue = '';
		$ret = $this->mix;
		$class = substr(get_class($this), 3);
		$class = substr($class, 0, strlen($class) - 3);
		foreach ($this->elems as $elem) {
			$ret .= $glue;
			$ret .= "<span class='$class' title='".htmlentities($elem->asHint())."'>";
			$ret .= $elem->visDump($flags);
			$ret .= "</span>";
			$glue = $this->glue;
		}
		if ($this->paren) $ret = '('.$ret.')';
		return $ret;
	}
}

class QRYjointset extends QRYset {
	protected $mix = '';
	protected $glue = ' ';
	
	public $priority = 40;
	
	public function visDump($flags=0) {
		return parent::visDump($flags & ~QRY::ALIAS_FIELDS);	
	}
	public function toString($flags=0) {
		return parent::toString($flags & ~QRY::ALIAS_FIELDS);	
	}
}

class QRYgroupset extends QRYset {
	protected $mix = 'GROUP BY ';
	protected $glue = '';

	public $priority = 80;
	
	public function visDump($flags=0) {
		return parent::visDump($flags & ~QRY::ALIAS_FIELDS);	
	}
	public function toString($flags=0) {
		return parent::toString($flags & ~QRY::ALIAS_FIELDS);	
	}	
}

class QRYelem {
	public function __toString() {		return $this->toString();	}
	public function asHint() {		return print_r($this, 1);	}
	public function visDump($flags=0) {	return $this->toString($flags);	}
}

class QRYtable extends QRYelem {
	private $name;	/* string */
	private $alias;	/* string */

	public function __construct($name, $alias) {
		$this->name = $name;
		$this->alias = $alias;
	}
	public function asName() {
		return $this->name;
	}
	public function asAlias() {
		return $this->alias;	
	}
	public function toString($flags=0) {
		return $this->name . 
			(($flags & QRY::ALIAS_TABLES) && 
				(!($flags & QRY::WEAK_ALIASES) || $this->alias != $this->name)
				 ? ' ' . $this->alias : '');
	}
}

class QRYparam extends QRYelem {
	private $ref;	/* string */
	private $offset;/* integer */

	public function __construct($ref, $offset) {
		$this->ref = $ref;
		$this->offset = $offset;
	}
	public function asName() {
		return $this->ref;	
	}
	public function toString($flags=0) {
		return ($flags & QRY::NAMED_PARAMS) ? ':'.$this->ref : '?';
	}
}

class QRYfield extends QRYelem {
	private $name;  	/* string */
	private $alias;	/* string */
	private $table; 	/* QRYtable pointer */
	private $order;	/* string */

	public function __construct($name, $alias, $table) {
		$this->name = $name;
		$this->alias = $alias;
		$this->table = $table;
	}
	public function setTable($table) {
		$this->table = $table;
	}
	public function setOrder($order) {
		if (!is_string($order)) throw new Exception('Argument 1 to setOrder must be a string, '.gettype($order).' given');
		$order = strtoupper($order);
		if ($order != 'ASC' && $order != 'DESC') throw new Exception('Argument 1 to setOrder must be "ASC" or "DESC"');
		$this->order = $order;
	}
	public function asTable() {
		return $this->table;	
	}
	public function asName() { 
		return $this->name; 
	}
	public function asAlias() {
		//if (!$this->table) throw new Exception("NULL table ref");
		return ($this->alias ? $this->alias : ($this->table ? $this->table->asAlias() .'__': ''/*null*/).$this->name);
	}
	public function asOrder() {
		return $this->order;
	}
	public function toString($flags=0) {
		return (($flags & QRY::ALIAS_TABLES) ? (!$this->table ? '~null~' : $this->table->asAlias()) . '.': '') 
			. $this->name 
			. (($flags & QRY::ALIAS_FIELDS) ? ' AS ' . $this->asAlias() : '')
			. ($this->order ? ' ' . $this->order : '');
	}
}

class QRYliteral extends QRYelem {
	private $val;	/* string */

	public $priority = 0;	

	public function __construct($val) {
		$this->val = $val;
	}
	public function toString() {
		return $this->val;
	}

	/* 3 out-of-place methods to work as part of QRYmatchset */
	public function setOrder($str) { $this->val .= ' ' . $str; }
	public function asName() { return $this->val; }
	public function asOrder() { return ''; }

}

class QRYmatch extends QRYelem {
	private $left;	/* QRYfield pointer */
	private $cmp;	/* string */
	private $right;	/* QRYparam pointer */

	public function __construct($left, $cmp, $right) {
		$this->left = $left;
		$this->cmp = $cmp;
		$this->right = $right;
	}

	public function setCmp($cmp) {
		$this->cmp = $cmp;
	}
	public function setRight($right) {
		$this->right = !$right ? new QRYliteral('~deleted~') : $right;
	}

	public function asCmp() {
		return $this->cmp;
	}
	public function getLeft() {
		return $this->left;
	}
	public function getRight() {
		return $this->right;
	}

	public function toString($flags=0, $single = null) {
		$flags &= ~QRY::ALIAS_FIELDS;
		$ret = '';
		if (!is_object($this->left)) throw new Exception('Corrupt QRYmatch:'.print_r($this,1));
		if ($single !== 'right')
		$ret .= $this->left->toString($flags);
		if ($single === null)
		$ret .= ' ' . $this->cmp . ' ';
		if ($single !== 'left')
		$ret .= $this->right->toString($flags);
		return $ret;
	}
	public function visDump($flags=0, $single = null) {
		$flags &= ~QRY::ALIAS_FIELDS;
		$ret = '';
		if ($single !== 'right') {
				if (!is_object($this->left)) throw new Exception('Corrupt QRYmatch:'.print_r($this,1));
			$class_left = substr(get_class($this->left), 3);
			$ret .= "<span class='$class_left' title='".htmlentities($this->left->asHint())."'>"
				.$this->left->toString($flags).'</span>';
		}
		if ($single === null) {
			$ret .= ' ' . $this->cmp . ' ';
		}
		if ($single !== 'left') {
			$class_right = substr(get_class($this->right), 3);
			$ret .= "<span class='$class_right' title='".htmlentities($this->right->asHint())."'>"
				.$this->right->toString($flags).'</span>';
		}
		return $ret;
	}
}

class QRYjoint extends QRYelem {
	private $left;	/* QRYfield pointer */
	private $right;	/* QRYfield pointer */

	public function __construct($left, /* $cmp,*/ $right) {
		$this->left = $left;
		$this->cmp = '=';
		$this->right = $right;
	}
	public function toString($flags = 0) {
		$ret = '';
		$ret .= 'LEFT JOIN ';
		$ret .= $this->right->asTable()->toString($flags);
		$ret .= ' ON ';
		$ret .= $this->left->toString($flags);
		$ret .= ' ' . $this->cmp . ' ';
		$ret .= $this->right->toString($flags);
		return $ret;
	}
	public function visDump($flags = 0) {
		$class_left = substr(get_class($this->left), 3);
		$ret = 'LEFT JOIN ';
		$ret .= "<span class='table' title='".htmlentities($this->right->asTable()->asHint())."'>";
		$ret .= $this->right->asTable()->visDump($flags);
		$ret .= "</span>";
		$ret .= ' ON ';
		$ret .= "<span class='$class_left' title='".htmlentities($this->left->asHint())."'>"
			.$this->left->toString($flags).'</span>';
		$ret .= ' ' . $this->cmp . ' ';
		$class_right = substr(get_class($this->right), 3);
		$ret .= "<span class='$class_right' title='".htmlentities($this->right->asHint())."'>"
			.$this->right->toString($flags).'</span>';

		return $ret;
	}
}

/*
 * Query Builder
 */
class QRY {

	private $script = array(); // actual query outline
	
	private $fields = array(); // QRYfield array
	private $params = array(); // QRYparam array
	private $tables = array(); // QRYtable array
	private $joints = array(); // QRYjoin array
	private $tablesets = array(); // QRYtableset array
	private $fieldsets = array(); // QRYfieldset array
	private $matchsets = array(); // QRYmatchset array
	private $jointsets = array(); // QRYjointset array
	private  $datasets = array(); // QRYdataset array
	private $valuesets = array(); // QRYvalueset array (insert/update pairs)
	private $ordersets = array(); // QRYorderset array (ORDER BY fields)
	private $groupsets = array(); // QRYgroupset array (GROUP BY fields)

	private $orphans = array('table'=>array());

	const ALIAS_TABLES = 0x0001; // prefix field names with table alias?
	const NAMED_PARAMS = 0x0002;
	const ALIAS_FIELDS = 0x0004;
	const WEAK_ALIASES = 0x1000; // skip aliases that are the same as real names

	private $flags = 0;

	/**
	 ***************************************************************************
	 ** POLYMORPHER
	 **
	 **/
	private $functional = array('SELECT', 'FROM', 'WHERE', 'DATA', 'VALUES', 'SET', 'INSERT', 'ORDER_BY', 'GROUP_BY', 'LIMIT');
	public function __call($name, $argv) {
		if (in_array(strtoupper($name), $this->functional)) {

			if (sizeof($argv) == 1) {
				if (is_array($argv[0])) {
					if (is_numeric(key($argv[0])))
						$this->{$name.'_array'}($argv[0]);
					else
						$this->{$name.'_hash'}($argv[0]);
				}
				else if (is_string($argv[0]) || is_numeric($argv[0]))
					$this->{$name.'_string'}($argv[0]);
				else throw new Exception('Argument 1 to '.$name.' must be a string or an array, yet it is a ' .gettype($argv[0])); 
			} else {
				call_user_func_array(
					array($this, $name.'_once'), 
					$argv
				);
			}
		} else throw new Exception('Inaccessible method ' .$name);
		return $this;
	}

	/**
	 ***************************************************************************
	 ** OPTIONS API
	 **
	 **/
	public function option($name, $value) {
		if (!in_array($name, array('named_params', 'alias_tables', 'alias_fields', 'weak_aliases'))) {
			throw new Exception("Undefined option '".$name."'");
		} else {
			$name = strtoupper($name);
			if ($value) {
				$this->flags |= constant('QRY::'.$name);	
			} else {
				$this->flags &= ~constant('QRY::'.$name);
			}	
		}
	}

	/**
	 ***************************************************************************
	 ** SETS and ELEMENTS API (internal)
	 **
	 **/
	public function getSet($type, $offset) {
		$arr = $type . 'sets';
		$size = count($this->{$arr});
		if (isset( $this->{$arr}[ $offset ] )) {
			return $this->{$arr}[ $offset ];
		}
		return NULL;
	}
	public function lastSet($type) {
		$arr = $type . 'sets';
		$size = count($this->{$arr});
		if ($size < 1) return NULL;
		return $this->{$arr}[ $size - 1 ];
	}
	public function firstSet($type) {
		$arr = $type . 'sets';
		$size = count($this->{$arr});
		if ($size < 1) return NULL;
		return $this->{$arr}[ 0 ];
	}
	public function lastElem($type) {
		$arr = $type . 's';
		$size = count($this->{$arr});
		if ($size < 1) return NULL;
		return $this->{$arr}[ $size - 1 ];
	}
	public function lastTable($offset = null) {
		if (isset($this->virt_tableset))
			$set = $this->virt_tableset;
		else
			$set = $this->lastSet('table');
		if (!$set) return NULL;
		if ($offset !== null) return $set->findByOffset($offset);
		return $set->last();
	}
	public function lastJointset() {	return $this->lastSet('joint'); }
	public function lastFieldset() {	return $this->lastSet('field'); }
	public function lastMatchset() {	return $this->lastSet('match'); }
	public function lastValueset() {	return $this->lastSet('match'); }
	public function lastDataset() {	return $this->lastSet('data'); }
	public function firstTableset() {return $this->firstSet('table'); }

	public function tableByAlias($alias) {
		//TODO: Replace with hash lookup
		foreach ($this->tables as $table) {
			if ($table->asAlias() == $alias) return $table;
		}
		return NULL;
	}
	public function tableByName($alias) {
		//TODO: Replace with hash lookup
	}
	public function findBy($type, $property, $value) {
		$arr = $type . 'sets';
		$found = NULL;
		//TODO: Replace with hash lookup
		foreach ($this->{$arr} as $set) {
			$found = $set->findBy($property, $value);
			if ($found) break;
		}
		return $found;
	}
	public function fieldByAlias($alias) {
		$found = $this->findBy('field', 'alias', $alias);
		//if (!$found) $found = $this->findBy('field', 'name', $alias);
		return $found;
	}

	public function add_SET($type) { 
		$class = 'QRY'.$type.'set';
		$arr = $type.'sets';

		$set = new $class();
		$this->{$arr}[] = $set;
		$this->scriptInsert($set);

		return $set;
	}
	public function add_FIELDSET() {	return $this->add_SET('field'); }
	public function add_ORDERSET() {	return $this->add_SET('order'); }
	public function add_MATCHSET() { return $this->add_SET('match'); }	
	public function add_TABLESET() { return $this->add_SET('table'); }
	public function add_DATASET() { 
		$set = new QRYdataset();
		$this->datasets[] = $set;
		return $set;
	}

	public function add_FIELD($field_name, $field_alias, $table, $fieldset) {
		$field = new QRYfield($field_name, $field_alias, $table);
		if ($fieldset) $fieldset->add($field);

		if (!$table) {
			$this->orphans['table'][] = $field;
		}

		return $field;
	}
	public function rem_PARAM() {
		//$set = $this->lastSet('param');
		if (count($this->params) <= 0) return;
		$last = $this->params[count($this->params) - 1];//$set->last();
		return array_pop($this->params);
		//$set->rem($last); 
	}
	public function rem_TABLE($table_ptr) { 
		foreach ($this->tablesets as $tableset) {
			$tableset->rem($table_ptr);
		}
	}
	public function add_TABLE($table_name, $table_alias, $tableset) { 

		if (!$table_alias) $table_alias = $table_name;// ?! :()

		$table = new QRYtable($table_name, $table_alias);
		$tableset->add($table);

		if (!isset($this->virt_tableset)) {
			$this->virt_tableset = new QRYtableset();
		}
		$this->virt_tableset->add($table);

		$skipped = array();
		while (count($this->orphans['table'])) {
			$elem = array_pop($this->orphans['table']);
			if (isset($elem->table_name) && $elem->table_name != $table_alias) {
				$skipped[] = $elem;
				continue;
			}
			$elem->setTable($table);
		}
		$this->orphans['table'] = $skipped;

		if ($this->virt_tableset->count() > 1) {
			$this->option('alias_tables', true);
		}

		$this->tables[] = $table;
	}
	public function add_JOIN($left, $right, $jointset) { //pointers to left and right QRYfield
		$cmp = '';
		$joint = new QRYjoint($left, $right);
		$jointset->add($joint);
	}
	public function add_MATCH($left, $cmp, $right, $matchset) { 
		$match = new QRYmatch($left, $cmp, $right);
		$matchset->add($match);
	}
	public function add_PARAM($param_name, $offset) {
		$param = new QRYparam($param_name, $offset);
		$this->params[] =& $param;

		if (isset($this->orphans['param']) && count($this->orphans['param'])) {
			$dataset = $this->orphans['param'][0];

			$elem = $dataset->findByKey($param_name);

			if ($elem == null) {
				$dataset->reset_key($offset, $param_name);
				array_shift($this->orphans['param']);
			}
		}

		return $param;
	}
	public function add_DATA($key, $value, $dataset) { 
		$dataset->add($value, $key);

		if ($key === null) {
			//echo "Marking for orphanage ($value)";
			$this->orphans['param'][] = $dataset; 
		}

	}

	private function force_TABLE($table_name, $table_alias = null) {
		/* Find/create relevant set */
		$tableset = $this->firstTableset() ;
		if (!$tableset) $tableset = $this->add_TABLESET();

		$this->add_TABLE($table_name, $table_alias, $tableset);		

		return $tableset;
	}
	private function force_MATCH($left, $cmp = '=', $right = null, $literal = false, $settype = 'match') {
		/* Find/create relevant set */
		$matchset = $this->lastSet($settype);
		if (!$matchset) $matchset = $this->add_SET($settype);

		/* Create FIELD for the let side */
		$left_ptr /*$fieldset*/ = $this->force_FIELD($left, null, null);
		//$left_ptr = $fieldset->last();	
		//$right_ptr = $this->paramByAlias($left);
		$right_ptr = null;
		if ($literal) $right_ptr = new QRYliteral($right);
		if (!$right_ptr) $right_ptr = $this->add_PARAM($left_ptr->asName(), count($this->params));

		/* Add 1 data point */
		if ($right !== null && !$literal) {
			$this->DATA_once($left, $right);
		}

		$this->add_MATCH($left_ptr, $cmp, $right_ptr, $matchset);

		return $matchset;
	}	
	private function force_FIELD($field_name, $left_table_offset = null, $settype = 'order') {
		/* Find/create relevant fieldset */
		$fieldset = NULL;
		if ($settype) {
			$fieldset = $this->lastSet($settype);
			if (!$fieldset) $fieldset = $this->add_SET($settype);
		}

		/* Hack -- if there are funky characters in the field name,
		 * treat it as literal */
		if (preg_match('#\(|\)#', $field_name)) {
			$field = new QRYliteral($field_name);//for this hack to work we added 3 methods to QRYliterl,see it
			$fieldset->add($field);
			//$left_ptr = $this->add_FIELD($field_name, null, null, $fieldset);
			//if ($fieldset == NULL) return $left_ptr;
			return $fieldset;
		}

		/* Add new field with those paramaters */
		$left_ptr = $this->fieldByAlias($field_name);
		if (!$left_ptr) {
			$field_alias = null;
			$table_ptr = $this->lastTable();
			$table_name = null;		
			if (strpos($field_name, '.') !== FALSE) {
				$field_alias = $field_name;
				list($table_name, $field_name) = preg_split('#\.#', $field_name);
				$table_ptr = $this->tableByAlias($table_name);
			}
			$left_ptr = $this->add_FIELD($field_name, $field_alias, $table_ptr, $fieldset);
			//hack -- if table_ptr is null, this was added to orphan
			if (!$table_ptr && $table_name) {
				//add 'orphan mark'
				$left_ptr->table_name = $table_name;
			}
		}
		if ($fieldset == NULL) return $left_ptr;
		return $fieldset;
	}
	
	/**
	 ***************************************************************************
	 ** SQL API
	 **
	 **/
	public function SELECT_once($field_name, $field_alias = null, $table_alias = null) {
		/* Get related table pointer */
		$table = $table_alias ? $this->tableByAlias($table_alias) : $this->lastTable() ;

		/* Find/create relevant set */
		$fieldset = $this->lastSet('field');
		if (!$fieldset) $fieldset = $this->add_SET('field');

		if ($field_alias)
			$this->option('alias_fields', true);

		/* Add new field with those paramaters */
		$this->add_FIELD($field_name, $field_alias, $table, $fieldset);
	}
	public function SELECT_hash($field_names_and_aliases) {
		foreach ($field_names_and_aliases as $field_name => $field_alias) {
			$this->SELECT_once($field_name, $field_alias);
		}
	}
	public function SELECT_array($field_names) {
		foreach ($field_names as $field_name) {
			$this->SELECT_once($field_name);
		}
	}
	public function SELECT_string($str) {

		/* By default, it is assumed string is field name and no aliases are present */
		$field_name = $str;
		$field_alias = null;
		$table_alias = null;

		/* Parse string to extract possible field alias (in form `field alias` or `field AS alias`) */
		$pot_field_aliases = preg_split("# AS |\s#", $field_name);
		if (count($pot_field_aliases) > 1) {
			$field_alias = $pot_field_aliases[1];
			$field_name =  $pot_field_aliases[0];
		}
		/* Parse string to extract possible table alias (in form `table.field`) */
		$pot_table_aliases = preg_split("#\.#", $field_name);
		if (count($pot_table_aliases) > 1) {
			$table_alias = $pot_table_aliases[0];
			$field_name =  $pot_field_aliases[1];
		}

		/* Actual function to do it is SELECT_once */
		$this->SELECT_once($field_name, $field_alias, $table_alias);
	}

	public function FROM_once($table_name, $table_alias = null) {
		return $this->force_TABLE($table_name, $table_alias);
	}
	public function FROM_array($table_names) {
		foreach ($table_names as $table_name) {
			$this->FROM_once($table_name);	
		}	
	}
	public function FROM_string($str) {
		/* By default, assume string is field name */
		$table_name = $str;
		$table_alias = '';

		/* But consider the possibility of "table alias" notation */
		if (strpos($str, ' ') !== FALSE) {
			list($table_name, $table_alias) = preg_split('#\s#', $str);
		}

		/* Actual operation */		
		$this->FROM_once($table_name, $table_alias);
	}

	public function INTO($table_name, $table_alias = null) {
		$tableset = $this->force_TABLE($table_name, $table_alias);
		$tableset->setMix('INTO ');
	}
	public function UPDATE($table_name, $table_alias = null) {
		$tableset = $this->force_TABLE($table_name, $table_alias);
		$tableset->setMix('');
		/* Change first event to UPDATE */
		$this->scriptForceHint('UPDATE');
	}

	public function WHERE_once($left, $cmp = '=', $right = null, $literal = false) {
		// need matchset for 'IN' hack
		$matchset = $this->lastMatchset();
		if (!$matchset) $matchset = $this->add_MATCHSET();
		//Horrible 'IN' hack
		if (strtoupper($cmp) == 'IN') {
			if (!is_array($right)) throw new Exception('Argument 3 must be an array, if argument 2 is "IN"');
			// make QRYset to hold IN values
			$middle_ptr = $this->IN_array($right, null);
//			$this->force_MATCH($left, $cmp, $middle_ptr, false, 'match');
//			return;
			$left_ptr /*$fieldset*/ = $this->force_FIELD($left, null, null);
			// add newly created set as right part of match
			$this->add_MATCH($left_ptr, $cmp, $middle_ptr, $matchset);
			return;
		}

		$this->force_MATCH($left, $cmp, $right, $literal, 'match');
	}
	public function WHERE_array($field_names) {
		foreach ($field_names as $field_name) {
			$this->WHERE_once($field_name);
		}
	}
	public function WHERE_hash($field_names_and_values) {
		foreach ($field_names_and_values as $name => $value) {
			$this->WHERE_once($name, '=', $value);
		}
	}
	public function WHERE_string($str) {

		// By default, assume str is field_name
		$field_name = $str;

		// But if we see some funky characters (like AND, OR or whitespace)
		if (preg_match('#\s|AND|OR|\(|\)#', $str)) {
			return $this->WHERE_literal($str);	
		}

		return $this->WHERE_once($str);
	}
	public function WHERE_literal($str) {
		$matchset = $this->lastMatchset();
		if (!$matchset) $matchset = $this->add_MATCHSET();

		$literset = new QRYset();
		$literset->setGlue('');
		$literset->setMix('');
		$literset->add(new QRYliteral($str));
		
		$matchset->setGlue('');
		$matchset->add($literset);		
	}

	public function VALUES_array($args) {
		return $this->DATA_array($args);
	}
	public function VALUES_hash($args) {
		return $this->DATA_hash($args);
	}
	private function VALUES_once($left, $cmp = '=', $right = null, $literal = false) {
		return $this->force_MATCH($left, $cmp, $right, $literal, 'value');
	}

	public function DATA_once($key, $value, $offset = null) {
		$dataset = $this->lastDataset();	
		if (!$dataset) $dataset = $this->add_DATASET();
		if ($offset === null) $offset = count($dataset->toArray());
		if ($this->flags & QRY::NAMED_PARAMS)
		{
			// If we need NAMED params, but key is NUMERIC,
			//try to get param by offset, and if it doesn't exist
			//(yet), mark this data point as orphan ($key=NULL)
			if (is_numeric($key))
			{
				if (!isset($this->params[$offset])) {
					$key = null;// Make it an orphan
				} else {
					$left_ptr = $this->params[$offset];
					$key = $left_ptr->asName();	
				}
			}
		} else {
			if (!is_numeric($key))
			{
				$key = $offset;				
			}	
		}
		$this->add_DATA($key, $value, $dataset);
	}	
	public function DATA_hash($args) {
		$this->option('named_params', true);
		return $this->DATA_array($args);
		$dataset = $this->add_DATASET();
		$offset = 0;
		foreach($args as $key => $value) {
			// If we need NUMERIC params, but key is STRING,
			if (!($this->flags & QRY::NAMED_PARAMS))
			{
				$key = $offset;
			}
			$this->add_DATA($key, $value, $dataset);
			$offset++;
		}
	}
	public function DATA_array($args) {
		$dataset = $this->add_DATASET();
		$offset = 0;
		foreach($args as $key => $value) {
			$this->DATA_once($key, $value, $offset);
			$offset++;
		}
	}
	public function DATA_string($str) {
		$dataset = $this->lastDataset();	
		if (!$dataset) $dataset = $this->add_DATASET();
		return $this->DATA_once(null, $str, null);
	}

	public function IN_array($values, $field_name=null, $literal=false) {
		$set = new QRYset(); 
		$set->setMix('');
		$set->setGlue(', ');
		$set->setParen(TRUE);

		$offset = 0;
		foreach ($values as $right) {
			$right_ptr = null;
			if ($literal) $right_ptr = new QRYliteral($right);
			if (!$right_ptr) $right_ptr = $this->add_PARAM($field_name, count($this->params));

			/* Add 1 data point */
			if ($right && !$literal) {
				$left = $field_name;//($field_name !== null ? $field_name : $offset);
				$this->DATA_once($left, $right);
			}
			$set->add($right_ptr);
			$offset++;
		}

		return $set;	
	}
	public function IN($values) {
		$matchset = $this->lastMatchset();
		if ($matchset) {
			$match = $matchset->last();
			$match->setCmp('IN');
			//mark for orphanage?	
		} else {
			throw new Exception("No match found, please use WHERE before IN");
		}
		// remove param... ?
		$param = $this->rem_PARAM();

		// create new IN QRYset
		$middle_ptr = $this->IN_array($values, $param ? $param->asName() : null);

		// replace newly created set as right `part of match
		$match->setRight($middle_ptr);
		return;
	}

	public function UPSERT() {

	}

	public function DELETE() {
		/* Change first event to DELETE */
		$this->scriptForceHint('DELETE');
	}

	public function INSERT_once($left, $cmp = '=', $right = null, $literal = false) {
		$matchset = $this->VALUES_once($left, $cmp, $right, $literal);
		$matchset->split_mode = true;
		$matchset->setMix(' VALUES ');
		$matchset->setGlue(', ');
		$matchset->setParen(TRUE); 
	}
	public function INSERT_array($field_names) {
		/* Change first event to INSERT */
		$this->scriptForceHint('INSERT');

		foreach ($field_names as $field_name) {
			$this->INSERT_once($field_name);	
		}
	}
	public function INSERT_string($field_name) {
		/* Change first event to INSERT */
		$this->scriptForceHint('INSERT');

		$this->INSERT_once($field_name);
	}

	public function SET_once($left, $right, $literal = false) {
		$matchset = $this->VALUES_once($left, '=', $right, $literal);
		$matchset->split_mode = false;
		$matchset->setMix('SET ');
		$matchset->setGlue(', ');
		$matchset->setParen(FALSE); 
	}
	public function SET_array($args) {
		foreach ($args as $field) {
			$this->SET_once($field, null);
		}
	}
	public function SET_string($field_name) {
		return $this->SET_once($field_name, null);	
	}
	public function SET_hash($field_names_and_values) {
		//Change first event to UPDATE
		//$this->scriptForceHint('UPDATE');
		if (!$field_names_and_values) throw new Exception("Attempting to SET an empty associative array");
		foreach ($field_names_and_values as $field_name => $value) {
			$this->SET_once($field_name, $value);
		}	
	}

	public function ON($left_field_name, $right_field_name, $left_table_offset = -2, $right_table_offset = null) {
		/* Find/create relevant set */
		$jointset = $this->lastSet('joint') ;
		if (!$jointset) $jointset = $this->add_SET('joint');

		$left_ptr = $this->fieldByAlias($left_field_name);
		if (!$left_ptr) $left_ptr = $this->add_FIELD($left_field_name, null, $this->lastTable($left_table_offset), null);

		$right_ptr = $this->fieldByAlias($right_field_name);
		if (!$right_ptr) $right_ptr = $this->add_FIELD($right_field_name, null, $this->lastTable($right_table_offset), null);

		//remove right_ptr->table from tableset?
		$this->rem_TABLE($right_ptr->asTable());

		$this->add_JOIN($left_ptr, $right_ptr, $jointset);
	}

	public function LIMIT_once($offset, $row_count) {
		$row_count = (int)$row_count;
		$literal = new QRYliteral(
			'LIMIT ' . 
			($offset !== null ? (int)$offset . ',' : '') .
			(int)$row_count
		);
		$literal->priority = 100;
		$this->scriptInsert($literal);
	}
	public function LIMIT_string($str) {
		/* By default, assume row_count */
		$row_count = (int)$str;
		$offset = null;

		/* Check for "offset, row_count" notation */
		if (preg_match('/(\d+)[, ]\s{0,2}(\d+)/', $str, $mc)) {
			list($tmp, $offset, $row_count) = $mc;
		} else 
		/* Check for "row_count OFFSET offset" notation */
		if (preg_match('/(\d+)\sOFFSET\s(\d+)/', $str, $mc)) {
			list($tmp, $row_count, $offset) = $mc;
		}

		$this->LIMIT_once($offset, $row_count);
	}

	public function ORDER_BY_once($field_name, $dir = 'ASC', $left_table_offset = null) {
		$fieldset = $this->force_FIELD($field_name, $left_table_offset, 'order');
		$left_ptr = $fieldset->last();
		$left_ptr->setOrder($dir);
	}
	public function ORDER_BY_array($field_names) {
		foreach ($field_names as $field_name) {
			$this->ORDER_BY_once($field_name);
		}
	}
	public function ORDER_BY_hash($field_names_and_dirs) {
		foreach ($field_names_and_dirs as $field_name => $dir) {
			$this->ORDER_BY_once($field_name, $dir);
		}
	}
	public function ORDER_BY_string($field_name, $dir = 'ASC', $left_table_offset = null) {
		return $this->ORDER_BY_once($field_name, $dir, $left_table_offset);
	}

	public function GROUP_BY_once($field_name, $left_table_offset = null) {
		$fieldset = $this->force_FIELD($field_name, $left_table_offset, 'group');
		$fieldset->setMix('GROUP BY ');
		$left_ptr = $fieldset->last();
	}
	public function GROUP_BY_string($field_name, $left_table_offset = null) {
		return $this->GROUP_BY_once($field_name, $left_table_offset);
	}

	private function drawscript($title) {
		echo "<h4>".$title."</h4>";
		foreach ($this->script as $i=>$obj) {
			echo "<li>".$i . ' ' . (is_object($obj) ? get_class($obj) : gettype($obj));	
		}
	}

	/* Hacky qry4-compatibility thing */
	public function LIKE($left_right_map, $literal = false) {
		$left_field_name = key($left_right_map);
		$right_field_name = current($left_right_map); 
		return $this->WHERE_once($left_field_name, 'LIKE', $right_field_name, $literal);
	}
	/* Hacky qry4-compatibility thing */
	public function ADD_ON($left_right_map, $left_table_offset = -2, $right_table_offset = null) {
		$left_field_name = key($left_right_map);
		$right_field_name = current($left_right_map); 
		return $this->ON($left_field_name, $right_field_name, $left_table_offset, $right_table_offset);
	}

	/**
	 ***************************************************************************
	 ** SCRIPT API (internal)
	 **
	 **/

	/* This function makes sure ->script start with a literal containing $hint */
	private function scriptForceHint($hint) {
		$hint = strtoupper($hint);
		$event = sizeof($this->script) > 0 ? $this->script[0] : null;
		if (!$event || $event->toString() != $hint) array_unshift($this->script, new QRYliteral($hint)); 
	}

	private function scriptInsert($object) {
		$priority = $object->priority;
		$j = null;
		foreach ($this->script as $i => $ref) {
			if ($priority < $ref->priority) {
				$j = $i;
				break;
			}
		}
		if ($j === null) $j = count($this->script);
		array_splice($this->script, $j, 0, array($object));
	}

	/**
	 ***************************************************************************
	 ** OUTPUT API
	 **
	 **/
	public function visDump() {
	static $dump_css = 1;
	if ($dump_css == 1)
echo <<<RAW_HTML
	<style>
	pre.qry span {
		display: inline-block;
		border: 1px solid black;
		margin: 3px;
	}
	pre.qry .field {
		background: #ccc;
		font-style: italic;
		border: none;
	}
	pre.qry .table {
		color: #333399;
		border: none;
	}
	pre.qry .match {
		background: #eee;
		border: none;
	}
	pre.qry .joint {
		background: #fee;
		border: none;
	}
	pre.qry .literal {
		text-decoration: underline;
		border: none;
	}
	</style>
RAW_HTML;
	$dump_css = 0;
	if (count($this->orphans['table'])) {
		echo "Warning: Have ".count($this->orphans['table']). " element(s) without a table refrence";
	}
		echo "<pre class='qry'>";
		foreach ($this->script as $elem) {
			$class = substr(get_class($elem), 3);
			echo "<span class='$class' title='".htmlentities($elem->asHint())."'>";
			echo $elem->visDump($this->flags);
			echo "</span>";
		}
		foreach ($this->datasets as $i=>$set) {
			echo "<div class='data'>";
			echo $set->visDump($this->flags, $i);
			echo "</div>";
		}
		echo "</pre><hr>";
	}

	public function applyFrom($q) {
		return $q->applyTo($this);
	}
	public function applyTo($q) {
//		private $jointsets = array(); // QRYjointset array
//		private $valuesets = array(); // QRYvalueset array (insert/update pairs)

		if ($this->flags & QRY::NAMED_PARAMS) {
			$q->option('named_params', 1);
		}

		foreach ($this->tablesets as $set) {
			for ($i = 0; $i < $set->count(); $i++) {
				$elem = $set->findByOffset($i);
				$q->FROM($elem->asName(), $elem->asAlias());
			}
		}

		foreach ($this->fieldsets as $set) {
			//print_r($set);
			for ($i = 0; $i < $set->count(); $i++) {
				$elem = $set->findByOffset($i);
				//print_r($elem);
				$q->SELECT($elem->asName());
			}
		}

		foreach ($this->matchsets as $j => $set) {
			for ($i = 0; $i < $set->count(); $i++) {
				$elem = $set->findByOffset($i);
				if (get_class($elem) == 'QRYset') {
					$q->WHERE_literal($elem->toString());
					continue;
				}
				$left_table = $elem->getLeft()->asTable();
				$left_name = $elem->getLeft()->asName();
				$left = $elem->getLeft()->asAlias();
				$right = $elem->getRight();
				$cmp = $elem->asCmp();
				$literal = false;
				$right_ptr = NULL;
				if ($cmp == 'IN' && get_class($right) == 'QRYset') {
					$param_arr = $right->toArray();
					$dataset = $this->getSet('data', 0);
					$dataset_arr = $dataset->toArray();
					$right_ptr = array();
					foreach ($param_arr as $offset => $param) {
						$name = $param->asName();
						if (isset($dataset_arr[$name])) {
							$right_ptr[] = $dataset_arr[$name];
						} else if (isset($dataset_arr[$offset])) {
							$right_ptr[] = $dataset_arr[$offset];
						}
					}
				}
				if ($left == $left_table . '__' . $left_name) {
					$left = $left_table . '.' . $left_name;
				}
				$q->WHERE_once($left, $cmp, $right_ptr, $literal);
			}
		}
		
		foreach ($this->ordersets as $set) {
			for ($i = 0; $i < $set->count(); $i++) {
				$elem = $set->findByOffset($i);
				$q->ORDER_BY($elem->asName(), $elem->asOrder());
			}
		}		

		foreach ($this->groupsets as $set) {
			for ($i = 0; $i < $set->count(); $i++) {
				$elem = $set->findByOffset($i);
				$q->GROUP_BY($elem->asName());
			}
		}		
		
		
		/* datasets */
		$matchset = $q->getSet('match', 0);	
		foreach ($this->datasets as $j => $set) {
			$dataset = $q->getSet('data', $j);	
			if (!$dataset) $dataset = $q->add_DATASET();
			$k = 0;
			for ($i = 0; $i < $set->count(); $i++) {
				$match = $matchset->findByOffset($k);
				if (get_class($match) == 'QRYmatch' && $match->asCmp() == 'IN') {					
					continue;
				}
				$elem = $set->findByOffset($i);
				$key = $set->keyByOffset($i);
				if (is_numeric($key)) $key = null;
				$q->add_DATA($key, $elem, $dataset); 
				$k++;
			}
			//$q->DATA($arr);

		}

		return true;
	}
	
	public function toString() {
		$ret = '';
		$space = '';
		foreach ($this->script as $elem) {
			$ret .= $space;
			$ret .= $elem->toString($this->flags);
			$space = ' ';
		}
//		foreach ($this->datasets as $i=>$set) {
//			$ret .= $set->toString();
//		}
		return $ret;
	}
	public function __toString() {
		return $this->toString();
	}
	public function toArray() {
		$ret = array();
		foreach ($this->datasets as $dataset) {
			$ret[] = $dataset->toArray();
		}
		return $ret;
	}
	public function toRun() {
		$str = $this->__toString();
		$arr = $this->toArray();

		return array($str => (is_array($arr) && sizeof($arr) == 1 ? $arr[0] : $arr));
	}
	public function toBatch() {
		return array( (string)$this => $this->toArray() ) ;
	}

	public function asFilter() {
		$dataset = $this->firstSet('data');
		$ret = array();
		foreach ($this->params as $i => $param) {
			$ret[$param->asName()] = $dataset->findByOffset($i);
		}
		return $ret;
	}
}

?>