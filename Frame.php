<?php

include('xstruct.php');

class Frame {
	public static	$header_size	= 16; /* How long the header is */
	private static	$header_struct	= "4sIII"; /* The structure of the header */
	private static	$header_array	= array("protocol", "sequence", "type", "length"); /* What the values map too */

	const OKAY		= 0;
	const FAIL		= 1;
	const SEQUENCE	= 2;
	const CONNECT	= 3;
	const LOGIN		= 4;

	const GAME		= 63;

	private static $data_structs = array(
		Frame::OKAY 	=> "S",
		Frame::FAIL 	=> "IS",
		Frame::SEQUENCE => "I",
		Frame::CONNECT	=> "S",
		Frame::LOGIN	=> "SS",

		Frame::GAME		=> "SS[S]SSSS[SSSI][ISI]"
	);
	
	private static $data_arrays = array(
		Frame::OKAY		=> array('message'),
		Frame::FAIL		=> array('type', 'desc'),
		Frame::SEQUENCE => array('no'),
		Frame::CONNECT	=> array('s'),
		Frame::LOGIN	=> array('username', 'password'),

		Frame::GAME		=> array('name', 'key', 'tp', 'server', 'sertype', 'rule', 'rulever', 'locations', 'optional'),
							//array('type', 'host', 'ip', 'port')
	);

	private $v = array();

	public function __construct($type=null, $sequence=null, $args=null) {
		if (is_null($type))
			return;

		if (!array_key_exists($type, Frame::$data_structs))
			throw new Exception("Unknown frame type $type.");
		$this->type = $type;

		if (!is_numeric($sequence))
			throw new Exception("Sequence must be numeric not $sequence.");
		$this->sequence = $sequence;

		foreach(Frame::$data_arrays[$this->type] as $key) {
			if (!array_key_exists($key, $args)) 
				throw new Exception("Did not have the require argument $key.");

			$this->$key = $args[$key];
			unset($args[$key]);
		}

		if (sizeof($args) > 0)
			throw new Exception("Got addition arguments!." . print_r($args, true));
	}

	public function __get($nm) {
		if (isset($this->v[$nm])) {
			return $this->v[$nm];
		}
		if (strcmp($nm, "length") === 0) {
			return strlen($this->pack_data());
		}
	}

	public function __set($nm, $val) {
		// FIXME: This should do some realtime type checking
		if ( in_array($nm, Frame::$header_array) or in_array($nm, Frame::$data_arrays[$this->type]) ){
			$this->v[$nm] = $val;
		} else
			throw new Exception("No such attribute can exist on this object.");
	}

	public function __isset($nm) {
		return isset($this->v[$nm]);
	}

	public function __unset($nm){
		unset($this->v[$nm]);
	}

	function parse_header($string) {
		if (strlen($string) != Frame::$header_size)
			throw new Exception("Header string size was not correct! Length was " . 
				strlen($string) . " required " . Frame::$header_size);

		list($temp, $string) = unpack_full(Frame::$header_struct, $string);
		$this->v = array_combine(Frame::$header_array, $temp);
	}

	function parse_data($string) {
		if (strlen($string) != $this->length)
			throw new Exception("Data string size was not correct! Length was " . 
				strlen($string) . " required " . $this->length);

		list($temp, $string) = unpack_full(Frame::$data_structs[$this->type], $string);

		$temp = array_combine(Frame::$data_arrays[$this->type], $temp);

		$this->v = array_merge($this->v, $temp);
	}

	function pack_data() {
		$args = array();
		foreach(Frame::$data_arrays[$this->type] as $key) 
			$args[] = $this->$key;

		return pack_full(Frame::$data_structs[$this->type], $args);
	
	}

	function pack() {
		$data = $this->pack_data();
		$header = pack_full(Frame::$header_struct, array("TP03", $this->sequence, $this->type, strlen($data)));
		return $header.$data;
	}

}

/*
print "<pre>";

$data = "TP03\x00\x00\x00\x01\x00\x00\x00\x03\x00\x00\x00\n\x00\x00\x00\x06peanut\0\0\0";

$f = new Frame();
print 'bh ' . bin2hex(substr($data, 0, Frame::$header_size)) . "\n";
$f->parse_header(substr($data, 0, Frame::$header_size));
print 'ah ' . bin2hex(substr($data, Frame::$header_size)) . "\n";
$f->parse_data(substr($data, Frame::$header_size, $f->length));
print 'ad ' . bin2hex($data) . "\n";
print_r($f);


$packed = $f->pack();
print 'original     ' . bin2hex($data) . " " . $f->length . "\n";
print 'packed       ' . bin2hex($packed) . " " . $f->length . "\n";

// Create one from scratch!
$f = new Frame(Frame::CONNECT, 1, array("s"=>"peanut"));
$packed = $f->pack();
print 'fresh packed ' . bin2hex($packed) . " " . $f->length . "\n";
print_r($f);
*/
