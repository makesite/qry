<?php
echo "<pre>";

define('TEST_QRY', 5);
require_once dirname(__FILE__) . "/simpletest/autorun.php";

include "qry5.php";

class TestOfQRY extends UnitTestCase {

	function testSimpleLiteral() {
		$q = new QRY();
		$q->SELECT('*');
		$q->FROM('authors');
		$q->WHERE('id = 1');
		$this->assertEqual( (string)$q, 'SELECT * FROM authors WHERE id = 1');
	}
	function testSimple() {
		$q = new QRY();
		$q->SELECT('*');
		$q->FROM('authors');
		$q->WHERE(array('id'=>1));
		//$q->WHERE_once('id', '=', 1);//qry5 b
		$this->assertEqual( $q->toRun(), array(
			'SELECT * FROM authors WHERE id = ?' => array(
				1
			)
		));
	}
	function testSelectLike() {
		$q = new QRY();
		$q->SELECT('*');
		$q->FROM('authors');
		$q->LIKE(array('name'=>"Bob"));
		//$q->WHERE_once('name', 'LIKE', 'Bob');//qry5
		$this->assertEqual( $q->toRun() ,
			array('SELECT * FROM authors WHERE name LIKE ?'
			=> array('Bob')	)
		);
	}

	function testSimpleInverse() {
		$q = new QRY();
		$q->FROM('authors');    	
		$q->SELECT('*');
		$q->WHERE('id = 1');
		$this->assertEqual( (string)$q, 'SELECT * FROM authors WHERE id = 1');
	}
	function testSimpleMixed() {
		$q = new QRY();
		$q->SELECT('*');
		$q->WHERE('id = 1');
		$q->FROM('authors');    	
		$this->assertEqual( (string)$q, 'SELECT * FROM authors WHERE id = 1');
	}
	function testSimpleMixed2() {
		$q = new QRY();
		$q->FROM('authors');
		$q->SELECT('*');
		$q->WHERE('id = 1');
		$this->assertEqual( (string)$q, 'SELECT * FROM authors WHERE id = 1');
	}

	function testMixedANDOR() {
		$q = new QRY();
		$q->FROM('authors');
		$q->SELECT('*');
		$q->WHERE('(');
		$q->WHERE('id');
		$q->DATA(1);
		$q->WHERE(' OR ');//qry5
		$q->WHERE('id');
		$q->DATA(2);
		$q->WHERE(')');
		$q->WHERE(' AND ');
		$q->WHERE('age');
		$q->DATA(5);
		$this->assertEqual( $q->toRun(), 
			//array('SELECT * FROM authors WHERE ( (id = ?) OR (id = ?) ) AND (age = ?)'=>//qry4
			array('SELECT * FROM authors WHERE (id = ? OR id = ?) AND age = ?'=>//qry5
				array(1,2,5)
			)
		);
	}

	function testSelectGreaterThan() {
		$q = new QRY();
		$q->FROM('authors');
		$q->SELECT('*');
		$q->WHERE("id > 1");
		$this->assertEqual( $q->toRun(), 
			array('SELECT * FROM authors WHERE id > 1'=>false
			)
		);
	}

	function testSingleIN() {
		$q = new QRY();
		$q->FROM('authors');
		$q->SELECT('*');
		$q->WHERE('id');
		$q->IN(array(1));
		$this->assertEqual( $q->toRun(), 
			//array('SELECT * FROM authors WHERE (id IN (?))'=>//qry4
			array('SELECT * FROM authors WHERE id IN (?)'=>//qry5
				array(1)
			)
		);
	}

	function testSimplestIN() {
		$q = new QRY();
		$q->FROM('authors');
		$q->SELECT('*');
		$q->WHERE('id');$q->IN(array(1,2));
		$this->assertEqual( $q->toRun(), 
			//array('SELECT * FROM authors WHERE (id IN (?, ?))'=>//qry4
			array('SELECT * FROM authors WHERE id IN (?, ?)'=>//qry5
				array(1,2)
			)
		);
	}

	function testSimpleIN() {
		$q = new QRY();
		$q->FROM('authors');
		$q->SELECT('*');
		$q->WHERE('id');$q->IN(array(1,2,3));
		$this->assertEqual( $q->toRun(), 
			//array('SELECT * FROM authors WHERE (id IN (?, ?, ?))'=>//qry4
			array('SELECT * FROM authors WHERE id IN (?, ?, ?)'=>//qry5
				array(1,2,3)
			)
		);
	}

	function testReversedIN() {
		$q = new QRY();
		$q->WHERE('id');$q->IN(array(1, 2, 3));
		$q->FROM('delay');
		$q->SELECT('*');
		$this->assertEqual( $q->toRun(), 
			//array('SELECT * FROM delay WHERE (id IN (?, ?, ?))'=>//qry4
			array('SELECT * FROM delay WHERE id IN (?, ?, ?)'=>//qry5
				array(1,2,3)
			)
		);
	}

	function testDoubleIN() {
		$q = new QRY();
		$q->FROM('authors');
		$q->SELECT('*');
		$q->WHERE('id');$q->IN(array(1,2));
		//$q->WHERE('AND');//qry4-specific
		$q->WHERE('age');$q->IN(array(9,10));
		$this->assertEqual( $q->toRun(), 
			//array('SELECT * FROM authors WHERE (id IN (?, ?)) AND (age IN (?, ?))'=>//qry4
			array('SELECT * FROM authors WHERE id IN (?, ?) AND age IN (?, ?)'=>//qry5
				array(1,2,9,10),
			)
		);
	}

	function testDataSimple() {
		$q = new QRY();
		$q->SELECT('*');
		$q->FROM('authors');
		$q->WHERE(array('id','age'));
		$q->DATA(array(5, 8));
		$q->DATA(array(6, 9));
		$q->DATA(array(7, 10));
		$this->assertEqual( $q->toRun() ,
			array('SELECT * FROM authors WHERE id = ? AND age = ?'=>
				array(
					array(5,8),
					array(6,9),
					array(7,10),
				),
			)
		);
	}

	function testDataSimpleNamed() {
		$q = new QRY();
		$q->SELECT('*');
		$q->FROM('authors');
		$q->WHERE(array('id','age'));

		$q->DATA(array('id'=>5, 8));
		$q->DATA(array(6, 9));
		$q->DATA(array(7, 10));

		$this->assertEqual( $q->toRun() ,
			array('SELECT * FROM authors WHERE id = :id AND age = :age'=>
				array(
					array('id'=>5, 'age'=>8),
					array('id'=>6, 'age'=>9),
					array('id'=>7, 'age'=>10),
				),
			)
		);
	}

	function testCombinedDataNamed() {
		$q = new QRY();
		$q->option('named_params', 1);
		$q->WHERE("y", '=', '2');

		$b = new QRY();
		$b->option('named_params', 1);		
		$b->WHERE("x", '=', '1');

		$b->applyFrom($q);
		
		$this->assertEqual( $b->toRun() ,
			array('WHERE x = :x AND y = :y'=>
				array('x'=>1, 'y'=>2),
			)
		);
	}

	function testDataDoubleNamed() {
		$q = new QRY();
		$q->option('named_params', 1);
		$q->SELECT('*');
		$q->FROM('authors');
		$q->WHERE('name', '=', 'Author Name');
		$q->FROM('books');
		$q->WHERE('name', '=', 'Book Title');

		$this->assertEqual( $q->toRun() ,
			array('SELECT * FROM authors WHERE id = :id AND age = :age'=>
				array(
					array('id'=>5, 'age'=>8),
					array('id'=>6, 'age'=>9),
					array('id'=>7, 'age'=>10),
				),
			)
		);
	}

	function testDataSimpleNamedLateMix() {
		$q = new QRY();
 		$q->SELECT('*');
		$q->FROM('authors');
		$q->DATA(array('id'=>6, 9));
		$q->WHERE(array('id','age'));

 		$this->assertEqual( $q->toBatch() ,
			array('SELECT * FROM authors WHERE id = :id AND age = :age'=>
				array(
					array('id'=>6, 'age'=>9),
				),
			)
		);
	}    

	function testDataComplexNamedLateMix() {
		$q = new QRY();
 		$q->SELECT('*');
		$q->FROM('authors');
		$q->DATA(array('id'=>6, 9));
		$q->WHERE(array('id','age'));
		$q->DATA(array('id'=>5, 8));
		$q->DATA(array(7, 10));

		$this->assertEqual( $q->toBatch() ,
			array('SELECT * FROM authors WHERE id = :id AND age = :age'=>
				array(
					array('id'=>6, 'age'=>9),
					array('id'=>5, 'age'=>8),
					array('id'=>7, 'age'=>10),
				),
			)
		);
	}    

	function testJoin() {
		$q = new QRY();
		$q->SELECT(array('id', 'title', 'body'));
		$q->FROM('articles');
		//$q->WHERE(array('id'=>1));//qry4
		$q->WHERE_once('id' ,'=', 1, true);//qry5
		$q->SELECT(array('name'));
		$q->FROM('authors');
		//$q->WHERE(array('level'=>0));
		//$q->ON(array('author_id'=>'id'));//qry4
		$q->ON('author_id', 'id');//qry5

		$this->assertEqual( (string) $q, 
			'SELECT articles.id, articles.title, articles.body, articles.name FROM articles articles LEFT JOIN authors authors ON articles.author_id = authors.id WHERE articles.id = 1');
	}

	function testJoinJoin() {
		$q = new QRY();
		$q->SELECT(array('id', 'title'));
		$q->FROM('threads');		
		//$q->WHERE(array('id'=>1));//qry4
		$q->WHERE_once('id', '=', 1, true);//qry5
		$q->FROM('posts');		
		$q->SELECT(array('id', 'title', 'body'));
		//$q->ON(array('id'=>'thread_id'));//qry4
		$q->ON('id', 'thread_id');//qry5
		$q->FROM('users');
		$q->SELECT(array('name'));
		//$q->ON(array('user_id'=>'id'));//qry4
		$q->ON('user_id', 'id');//qry5

		$this->assertEqual( (string) $q, 
			'SELECT threads.id, threads.title, posts.id, posts.title, posts.body, users.name FROM threads threads LEFT JOIN posts posts ON threads.id = posts.thread_id LEFT JOIN users users ON posts.user_id = users.id WHERE threads.id = 1');
	}

	function testJoinAndJoin() {
		$q = new QRY();
		$q->SELECT(array('id', 'title', 'body'));
		$q->FROM('posts');		
		//$q->WHERE(array('id'=>1));//qry4
		$q->WHERE_once('id', '=' , 1, true);//qry5
		$q->FROM('threads');	
		$q->SELECT(array('id', 'title'));
		$q->ADD_ON(array('thread_id'=>'id'));//qry4
		//$q->ON('thread_id', 'id');//qry5
		$q->FROM('users');
		$q->SELECT(array('name'));
		$q->ADD_ON(array('user_id'=>'id'), 0);//qry4
		//$q->ON('user_id', 'id', 0, -1);//qry5

		$this->assertEqual( (string) $q, 
			'SELECT posts.id, posts.title, posts.body, threads.id, threads.title, users.name FROM posts posts LEFT JOIN threads threads ON posts.thread_id = threads.id LEFT JOIN users users ON posts.user_id = users.id WHERE posts.id = 1');
	}

	function testJoinRecursive() {
		$q = new QRY();
		$q->SELECT(array('id'));
		$q->FROM('nodes nodes0');//qry4
		//$q->FROM('nodes', 'nodes0');//qry5		
		//$q->WHERE(array('id'=>1));//qry4
		$q->WHERE_once('id', '=' , 1, true);//qry5

		$q->FROM('nodes nodes1');
		$q->SELECT(array('id'));
		$q->ADD_ON(array('parent_id'=>'id'));

		$q->FROM('nodes nodes2');
		$q->SELECT(array('id'));
		$q->ADD_ON(array('parent_id'=>'id'));

		$q->FROM('cubes cubes0');//qry4
		$q->SELECT(array('color'));		
		$q->ADD_ON(array('node_id'=>'id'), 0);

		$q->FROM('cubes cubes1');//qry4
		$q->SELECT(array('color'));
		$q->ADD_ON(array('node_id'=>'id'), 1);

		$q->FROM('cubes cubes2');
		$q->SELECT(array('color'));
		$q->ADD_ON(array('node_id'=>'id'), 2);

/*
$x = (string) $q;
$x = str_replace(
array("SELECT", "FROM", "LEFT", "WHERE"),
array("\nSELECT", "\nFROM", "\nLEFT", "\nWHERE"), $x);
echo $x."\n";
*/
		$this->assertEqual( (string) $q, 
	  		'SELECT nodes0.id, nodes1.id, nodes2.id, cubes0.color, cubes1.color, cubes2.color '
	  		.'FROM nodes nodes0 '
	  		.'LEFT JOIN nodes nodes1 ON nodes0.parent_id = nodes1.id '
	  		.'LEFT JOIN nodes nodes2 ON nodes1.parent_id = nodes2.id '
	  		.'LEFT JOIN cubes cubes0 ON nodes0.node_id = cubes0.id '
	  		.'LEFT JOIN cubes cubes1 ON nodes1.node_id = cubes1.id '
	  		.'LEFT JOIN cubes cubes2 ON nodes2.node_id = cubes2.id '
	  		.'WHERE nodes0.id = 1'
		);
	}

	function testInsertSimple() {
 		$q = new QRY();
		$q->INSERT(array('login', 'pass'));
		$q->INTO('users');
		$q->VALUES(array('user1', 'secrecy'));
		$this->assertEqual( $q->toBatch() ,
			array('INSERT INTO users (login, pass) VALUES (?, ?)'=>
				array(					array('user1', 'secrecy'),		),	)
		);
	}

	function testInsertSimpleMulti() {
		$q = new QRY();
		$q->INTO('users');
		$q->INSERT(array('login', 'pass'));
		$q->DATA(array('root', 'secret'));    	
		$q->DATA(array('user1', 'dog'));
		$q->DATA(array('user2', 'cat'));
		$this->assertEqual( $q->toBatch() ,
			array('INSERT INTO users (login, pass) VALUES (?, ?)'=>
				array(
					array('root', 'secret'),
					array('user1', 'dog'),
					array('user2', 'cat'),
				),	)
		);
	}

	function testUpdateSimple() {
		$q = new QRY();
		$q->UPDATE('users');
		$q->SET(array('login', 'pass'));
		$q->VALUES(array('user1', 'secrecy'));
		$q->WHERE(array('id'));
		$q->DATA(1);
		$this->assertEqual( $q->toBatch() ,
			array('UPDATE users SET login = ?, pass = ? WHERE id = ?'=>
				array(					array('user1', 'secrecy', 1),		),	)
		);
	}

	function testUpdateSplit() {	
		$q = new QRY();
		$q->UPDATE('posts');
		$q->SET(array('ogg_ready', 'png_ready', 'modified'));
		$q->VALUES(array(1, 2, 64));
		$q->WHERE('id');
		$q->DATA(99);
	}

	function testDeleteSimple() {
		$q = new QRY();
		$q->DELETE();
		$q->FROM('users');
		$q->WHERE(array('id' => 1));
		$this->assertEqual( $q->toRun() ,
			array('DELETE FROM users WHERE id = ?'=>
				array(1),
		));
	}	

	function testAssortedInsert() {
		$q = new QRY();
		$q->INSERT(array('author_id','title','body'));
		$q->INTO('some_table');
		$q->VALUES(array(1, 'Gadlo', ''));
		$this->assertEqual( $q->toRun(),
			array('INSERT INTO some_table (author_id, title, body) VALUES (?, ?, ?)'=>
				array(1, 'Gadlo', '')
			)
		);
	}

	function testWhereData() {
		$q = new QRY(); 

		$filters = array('parent_id' => 0, 'board_id' => 1);
		$keys = array_keys($filters);

		$q->WHERE($keys);
		$q->DATA($filters);
		$q->FROM('whatever');

		$this->assertEqual( $q->toRun() ,
			array('FROM whatever WHERE parent_id = :parent_id AND board_id = :board_id' => $filters)	
		);
	}

	function testOrderBy() {
		$q = new QRY();
		$q->ORDER_BY('age');
//echo "<div style='background: #f99; padding: 30px;'>";$q->visDump();
		$q->SELECT('*');
		$q->FROM('users');
		$this->assertEqual( $q->toRun() ,
			array('SELECT * FROM users ORDER BY age ASC' => false)	
		);
//echo "</div>";
	}

	function testLimit() {
		$q = new QRY();
		$q->LIMIT(1, 10);
		$q->SELECT('*');
		$q->FROM('table');
		$this->assertEqual( $q->toRun() ,
			array('SELECT * FROM table LIMIT 1,10' => false)	
		);
	}
	function testLimitParsed1() {
		$q = new QRY();
		$q->LIMIT('1, 10');
		$q->SELECT('*');
		$q->FROM('table');
		$this->assertEqual( $q->toRun() ,
			array('SELECT * FROM table LIMIT 1,10' => false)	
		);
	}
	function testLimitParsed2() {
		$q = new QRY();
		$q->LIMIT('10 OFFSET 1');
		$q->SELECT('*');
		$q->FROM('table');
		$this->assertEqual( $q->toRun() ,
			array('SELECT * FROM table LIMIT 1,10' => false)	
		);
	}
	function testGroupBy() {
		$q = new QRY();
		$q->GROUP_BY('age');
		$q->SELECT('*');
		$q->FROM('users');
		$this->assertEqual( $q->toRun() ,
			array('SELECT * FROM users GROUP BY age' => false)	
		);
	}

	function testFieldAlias() {
		$q = new QRY();
		$q->SELECT("mobster AS long_mobs");
		$this->assertEqual( (string)$q, 'SELECT mobster AS long_mobs');
	}

	function testFieldAliasOff() {
		$q = new QRY();
		$q->SELECT("mobster AS long_mobs");
		$q->option('alias_fields', false);
		$this->assertEqual( (string)$q, 'SELECT mobster');
	}

	function testParseFrom() {
		$q = new QRY();
		$q->SELECT("field");
		$q->FROM("table alias");
		$q->option('alias_tables', true);
		$this->assertEqual( (string)$q, 'SELECT alias.field FROM table alias');
	}

	function testAliasFieldsInOtherSets() {
		$q = new QRY();
		$q->SELECT("field");
		$q->FROM("table alias");
		$q->ORDER_BY("field2");
		$q->GROUP_BY("field3");
		$q->WHERE("field4 = 5");
		$q->option('alias_fields', true);

		$this->assertEqual( (string)$q,
		'SELECT field AS alias__field FROM table WHERE field4 = 5 GROUP BY field3 ORDER BY field2 ASC');
	}	
	
	function testAsFilter() {
		$q = new QRY();
		$q->WHERE_once("id", '=', 1);
		$this->assertEqual( $q->asFilter(), array('id' => 1));	
	}
	
	function testDelayedAliasedParam() {
		$q = new QRY();
		$q->WHERE('main.id', '=', '1');//mess up
		$q->FROM('books');
		$q->FROM('authors main');
		$q->ON('author_id', 'id');

		$this->assertEqual( $q->toRun(), array('FROM books books LEFT JOIN authors main ON books.author_id = main.id WHERE main.id = ?' => array(1)) );
	}

	function testSimpleEquiv() {
		$a = new QRY();
		$b = new QRY();
		$a->SELECT("field");
		$a->FROM("tablica");
		$a->applyTo($b);
		$this->assertEqual( $a->toRun(), $b->toRun() );
	}
	
	function testWhereInEquiv() {
		$a = new QRY();
		$b = new QRY();
		$a->SELECT("field");
		$a->FROM("tablica");
		$a->WHERE('id', 'IN', array(1,2));
		$a->applyTo($b);
		$this->assertEqual( $a->toRun(), $b->toRun() );
	}	
	
	function testApplyOrder() {
		$a = new QRY();
		$b = new QRY();
		$a->ORDER_BY("field", "ASC");
		$a->applyTo($b);
		$this->assertEqual( $a->toRun(), $b->toRun() );

	}
	
	function testApplyGroup() {
		$a = new QRY();
		$b = new QRY();
		$a->GROUP_BY("field");
		$a->applyTo($b);
		$this->assertEqual( $a->toRun(), $b->toRun() );
	}	
	
	function testDelayApply() {
		$a = new QRY();
		$a->LIKE(array("files1.nomen" => "%gadl%"));

		$b = new QRY();
		$b->SELECT("nomen");
		$b->FROM("files1");
		$b->applyFrom($a);

		//$b->option('alias_tables', true);
		//$b->option('weak_aliases', true);
		$this->assertEqual( $b->toRun(), array( 'SELECT nomen FROM files1 WHERE nomen LIKE ?' => array('%gadl%') ) );
	}

	function testDelayApplyDoubleNamed() {
		$a = new QRY();
		$a->LIKE(array("files1.nomen" => "%gadl%"));
		$a->LIKE(array("files2.nomen" => "%gadl%"));

		$b = new QRY();
		$b->SELECT("nomen");
		$b->FROM("files1");
		$b->FROM("files2");
		$b->applyFrom($a);

		//$b->option('alias_tables', true);
		$b->option('weak_aliases', true);
		$this->assertEqual( $b->toRun(), array( 
			'SELECT files1.nomen FROM files1, files2 WHERE files1.nomen LIKE ? AND files2.nomen LIKE ?' => 
				array('%gadl%', '%gadl%')
		 ) );
	}
	function testDelayApplyOR() {
		$a = new QRY();
		$a->LIKE(array("files1.nomen" => "%gadl%"));
		$a->WHERE(' OR ');		
		$a->LIKE(array("files2.nomen" => "%gadl%"));

		$b = new QRY();
		$b->SELECT("nomen");
		$b->FROM("files1");
		$b->FROM("files2");
		$b->applyFrom($a);

		//$b->option('alias_tables', true);
		$b->option('weak_aliases', true);
		$this->assertEqual( $b->toRun(), array( 
			'SELECT files1.nomen FROM files1, files2 WHERE files1.nomen LIKE ? OR files2.nomen LIKE ?' => 
				array('%gadl%', '%gadl%')
		 ) );
	}
}

?>