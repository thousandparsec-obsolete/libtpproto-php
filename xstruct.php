<?php

/*
This is a clone of the xstruct.py module found in libtpproto-py.

Everything is assumed to be network order, ie you don't need to
prepend every struct with !

Normal stuff from the struct module:

 c	Char
 b	Int8		(8 bit integer)
 B	UInt8		(8 bit unsigned integer)
 h	Int16		(16 bit integer)
 H	UInt16		(16 bit unsigned integer)
 i	Int32		(32 bit integer)
 I	UInt32		(32 bit unsigned integer)
 q	Int64		(64 bit integer)
 Q	UInt64		(64 bit unsigned integer)
 f	float		(32 bit floating point number)
 d	double		(64 bit floating point number)

Extra stuff defined by this module:

 S	String
 Y	Padded String	
 [	List Start			(unsigned int32 length)
 ]	List End	
 {  List Start			(unsigned int64 length)
 }  List End
 n  SInt16				(16 bit semi-signed integer)
 j  SInt32				(32 bit semi-signed integer)
 p  SInt64				(64 bit semi-signed integer)
 
The structure of the data in the list is described by the data inside the
brackets.

Example:
	[L] would be a list of unsigned longs
	It is actually transmitted as <length><data><data><data>
	
Obviously you can't calculate size of an xstruct string. The unpack
function will return the unused data.

*/

/* Direct mapping between xstruct constants and pack/unpack */
/*$mapping = [
 'c' => 'c',
 'b' => 'c',
 'B' => 'C',
 'H' => 'n',
]; */

function unpack_Int($bytes, $string, $signed = true) {
	$result = bcadd(0, 0);
	for ($i = 0; $i < $bytes; $i++) {
		if ($i == 0 and $signed)
			$bits = unpack('cbits',$string[$i]);
		else
			$bits = unpack('Cbits',$string[$i]);

		if (bccomp(0, $result) == 1)
			$bits['bits'] = -1*$bits['bits'];
			
		$result = bcadd(bcmul($result, pow(2, 8)), $bits['bits']);
	}
	return array($result, substr($string, $bytes));
}

function pack_Int($bytes, $value, $signed = true) {
	if (bccomp($value, bcpow(2, 8*$bytes-$signed)) != -1)
		throw new Exception("Integer to pack is to large!");

	$result = "";
	for ($i = $bytes-1; $i >= 0 ; $i--) {
		$byte = bcdiv($value, bcpow(2, 8*$i));
		$value = bcsub($value, bcmul($byte, bcpow(2, 8*$i)));

		if ($i == $bytes-1 and $signed)
			$result .= pack('c', $byte);
		else
			$result .= pack('C', $byte);
	}
	return $result;
}


$ints = array('b' => 1, 'h' => 2, 'i' => 4, 'q' => 8, 'n' => 2, 'j' => 4, 'p' => 8);
$sints = "njp";

/*
Takes a structure string and the arguments to pack in the format specified by the string.
*/
function pack_full($struct, $args) {
	global $ints, $sints;

	$result = "";
	while (strlen($struct) > 0) {
		$char = $struct[0];
		$struct = substr($struct, 1);

		if ($char == ' ' or $char == '!') {
			continue;
		} else if ($char == '{') {
			$end = strpos($struct, '}');
			$substruct = substr($struct, 0, $end);
			$struct = substr($struct, $end+1);
	
	
			$result .= pack_list('L', $substruct, array_shift($args));
		} else if ($char == '[') {
			$end = strpos($struct, ']');
			$substruct = substr($struct, 0, $end);
			$struct = substr($struct, $end+1);

			$result .= pack_list('I', $substruct, array_shift($args));
		} else if ($char == 'S') {
			$result .= pack_string(array_shift($args));
		} else if (ctype_digit($char)) {
			while (ctype_digit($char)) {
				$char .= $struct[0];
				$struct = substr($struct, 1);
			}

			$number = substr($char, strlen($char)-2);
			$char = $char[strlen($char)-1];

			if ($char == 's') {
				$arg = array_shift($args);
				if (strlen($arg) == $number)
					$result .= $arg;
				else
					throw new Exception("hrm.............");
			} else if ($char == 'x') {
				for ($i = 0 ; $i < $number; $i++)
					$result .= "\0";
			} else {
				for ($i = 0; $i < $number; $i++) {
					$result .= pack_full($char, array(array_shift($args)));
				}
			}
		} else if (array_key_exists(strtolower($char), $ints)) {
			$unsigned = ctype_upper($char) or (strpos($char, $sints) != false);
			$result .= pack_Int($ints[strtolower($char)], array_shift($args), !$unsigned);

		} else {
			# Unsupported for the moment (f, d)
			throw new Exception("Currently don't support f or d");
		}
	}
	return $result;
}

/* Takes a structure and a string, returns the array of detail and the left over string */
function unpack_full($struct, $string) {
	global $ints, $sints;

	$result = array();
	while (strlen($struct) > 0) {
		$char = $struct[0];
		$struct = substr($struct, 1);

		if ($char == ' ' or $char == '!') {
			continue;
		} else if ($char == '{') {
			$end = strpos('}', $struct);
			$substruct = substr($struct, 0, $end);
			$struct = substr($struct, $end);
			
			list($result[], $string) = unpack_list('L', $substruct, $string);
			
		} else if ($char == '[') {
			$end = strpos(']', $struct);
			$substruct = substr($struct, 0, $end);
			$struct = substr($struct, $end);
			
			list($result[], $string) = unpack_list('I', $substruct, $string);
		} else if ($char == 'S') {
			list($result[], $string) = unpack_string($string);
		} else if (ctype_digit($char)) {
			while (ctype_digit($char)) {
				$char .= $struct[0];
				$struct = substr($struct, 1);
			}

			$number = substr($char, strlen($char)-2);
			$char = $char[strlen($char)-1];

			if ($char == 's') {
				$result[] = substr($string, 0, $number);
				$string = substr($string, $number);
			} else if ($char == 'x') {
				$string = substr($string, $number);
			} else {
				for ($i = 0; $i < $number; $i++) {
					list($temp, $string) = unpack_full($char, $string);
					$result[] = $temp[0];
				}
			}
		} else if (array_key_exists(strtolower($char), $ints)) {
			$unsigned = ctype_upper($char) or strpos($char, $sints) != false;
			list($result[], $string) = unpack_Int($ints[strtolower($char)], $string, !$unsigned);
		} else {
			# Unsupported for the moment (f, d)
			throw new Exception("Currently don't support f or d");
		}
	}
	return array($result, $string);
}

/*
print bin2hex(pack_Int(4, bcpow(2, 31), false));
print "\n-----------------------\n";

list($result, $string) = unpack_full("II", "\x00\x00\x00\x01\x00\x00\x00\x02");
print_r($result);
print_r($string);

print "\n\n";
$s = pack_full("iI", array(bcsub(bcpow(2, 31), 1), bcsub(bcpow(2, 32), 1)));
print bin2hex($s) . "\n";
print "\n\n";

list($result, $string) = unpack_full("2I", $s);
print_r($result);
print_r($string);
*/

function pack_string($s) {
	return pack_full('I', array(strlen($s))) . $s;
}

function unpack_string($s) {
	list($length, $s) = unpack_full('I', $s);

	$r = substr($s, 0, $length[0]);
	// Remove any null terminator
	if ($r[strlen($r)] == "\0")
		$r = substr($r, 0, strlen($r)-1);

	return array($r, substr($s, $length[0]));
}

function pack_list($h, $struct, $args) {
	$s = pack_full($h, array(count($args)));
	foreach($args as $arg) {
		if (strlen($struct) == 1)
			$arg = array($arg);
		$s .= pack_full($struct, $arg);
	}
	return $s;
}

/*
def pack_list(length_struct, struct, args):
	"""\
	*Internal*

	Packs the id list so it can be send.
	"""
	# The length
	output = pack(length_struct, len(args))

	# The list
	for id in args:
		if type(id) == ListType or type(id) == TupleType:
			args = [struct] + list(id)
			output += apply(pack, args)
		else:
			output += pack(struct, id)
		
	return output

def unpack_list(length_struct, struct, s):
	"""\
	*Internal*

	Returns the first string from the input data and any remaining data.
	"""
	output, s = unpack(length_struct, s)
	length, = output

	list = []
	for i in range(0, length):
		output, s = unpack(struct, s)
		if len(output) == 1:
			list.append(output[0])
		else:
			list.append(output)

	return list, s

*/
