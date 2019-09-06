 <?php
$api_prefix="/showhuai-api/v1.0";

$dbuser = "root"; $dbpass = "mysql"; $dbhost = "localhost"; $dbname = "showhuai";
$cid = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname) or die("Unable to connect to MySQL or database");

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
require __DIR__ . '/../../slim/vendor/autoload.php';

$app = AppFactory::create();

// Get ALL product info
$app->get($api_prefix.'/product', function (Request $request, Response $response, $args) {
	$info=$request->getQueryParams();
	if(!check_apikey($info['api_key'])) {
		$res['status']='NOAUTH';
		$json=json_encode($res,JSON_PRETTY_PRINT);
		$response->getBody()->write($json);
		return $response;
	}
	$json=json_encode(get_product(),JSON_PRETTY_PRINT);
	$response->getBody()->write($json);
    return $response;
});
// Get product info (can also get a specific field)
$app->get($api_prefix.'/product/{id:[0-9]+}', function (Request $request, Response $response, $args) {
	$info=$request->getQueryParams();
	if(!check_apikey($info['api_key'])) {
		$res['status']='NOAUTH';
		$json=json_encode($res,JSON_PRETTY_PRINT);
		$response->getBody()->write($json);
		return $response;
	}
	$json=json_encode(get_product($args['id'],strtolower($info['field'])),JSON_PRETTY_PRINT);
	$response->getBody()->write($json);
    return $response;
});
// Add new product
$app->post($api_prefix.'/product', function (Request $request, Response $response, $args) {
	$info=$request->getQueryParams();
	if(!check_apikey($info['api_key'])) {
		$res['status']='NOAUTH';
		$json=json_encode($res,JSON_PRETTY_PRINT);
		$response->getBody()->write($json);
		return $response;
	}
	$info=$request->getParsedBody();
	$json=json_encode(add_product($info),JSON_PRETTY_PRINT);
	$response->getBody()->write($json);
    return $response;
});

// Update a specific field of product (for only QTY field [eq:"=3" add:"3" minus:"-3"])
$app->patch($api_prefix.'/product/{id:[0-9]+}', function (Request $request, Response $response, $args) {
	$info=$request->getQueryParams();
	if(!check_apikey($info['api_key'])) {
		$res['status']='NOAUTH';
		$json=json_encode($res,JSON_PRETTY_PRINT);
		$response->getBody()->write($json);
		return $response;
	}
	unset($info['api_key']);
	$json=json_encode(adjust_product($args['id'],$info),JSON_PRETTY_PRINT);
	$response->getBody()->write($json);
    return $response;
});

// Delete a product
$app->delete($api_prefix.'/product/{id:[0-9]+}', function (Request $request, Response $response, $args) {
	$info=$request->getQueryParams();
	if(!check_apikey($info['api_key'])) {
		$res['status']='NOAUTH';
		$json=json_encode($res,JSON_PRETTY_PRINT);
		$response->getBody()->write($json);
		return $response;
	}
	$json=json_encode(delete_product($args['id']),JSON_PRETTY_PRINT);
	$response->getBody()->write($json);
    return $response;
});

// Add new sale
$app->post($api_prefix.'/sale', function (Request $request, Response $response, $args) {
	$info=$request->getQueryParams();
	if(!check_apikey($info['api_key'])) {
		$res['status']='NOAUTH';
		$json=json_encode($res,JSON_PRETTY_PRINT);
		$response->getBody()->write($json);
		return $response;
	}
	$info=$request->getParsedBody();
	$json=json_encode(add_sale($info),JSON_PRETTY_PRINT);
	$response->getBody()->write($json);
    return $response;
});

// Get sale info by specific product id
$app->get($api_prefix.'/sale/{id:[0-9]+}', function (Request $request, Response $response, $args) {
	$info=$request->getQueryParams();
	if(!check_apikey($info['api_key'])) {
		$res['status']='NOAUTH';
		$json=json_encode($res,JSON_PRETTY_PRINT);
		$response->getBody()->write($json);
		return $response;
	}
	$json=json_encode(get_sale($args['id']),JSON_PRETTY_PRINT);
	$response->getBody()->write($json);
    return $response;
});

$app->run();

function check_apikey($key) {
	global $cid;
	$sql="SELECT * FROM api WHERE upper(apikey)='".strtoupper($key)."'";
	$result = mysqli_query($cid, $sql);
	if(!$result) {
		$auth=false;
	} else {
		if (mysqli_affected_rows($cid)<1)
			$auth=false;
		else
			$auth=true;
	}
	return $auth;
}

function get_product($id=null,$field=null) {
	global $cid;
	if(!empty($id))
		$sql="SELECT * FROM product WHERE id='{$id}'";
	else
		$sql="SELECT * FROM product";
	$result = mysqli_query($cid, $sql);
	if(!$result) {
		$res['error']=mysqli_error($cid);
		$res['status']='FAIL';
	} else if (mysqli_affected_rows($cid)<1) {
		$res['status']='NONE';
	} else {
		$count=0;
		while($row=mysqli_fetch_assoc($result)) {
			if(empty($field))
				$data[]=$row;
			else {
				$data['id']=$row['id'];
				$data[$field]=$row[$field];
			}
			$count++;
		}
		$res['number']=$count;
		$res['data']=$data;
		$res['status']='OK';
	}
	mysqli_close($cid);
	return $res;
}

function add_product($info) {
	global $cid;
	$sql="INSERT INTO product(id, name, price) VALUES ('{$info['id']}', '{$info['name']}', {$info['price']})";
	$result = mysqli_query($cid, $sql);
	if(!$result) {
		$res['error']=mysqli_error($cid);
		$res['status']='FAIL';
	} else {
		$res['status']='OK';
	}
	mysqli_close($cid);
	return $res;
}

function adjust_product($id,$info) {
	global $cid;
	$attr[strtolower(key($info))]=$info[key($info)];

	switch(key($attr)) {
		case 'qty'  :  	$sign=$attr[key($attr)][0];
						if($sign=='=')
							$strval=(int)ltrim($attr[key($attr)],'=');
						else {
							$qty_val=(int)$attr[key($attr)];
							if($qty_val>=0)
								$strval="qty+".$qty_val;
							else
								$strval="qty".$qty_val;
						}
						break;
		case 'id'	: $strval="'".$attr[key($attr)]."'"; break;
		case 'name'	: $strval="'".$attr[key($attr)]."'"; break;
		default : $strval=$attr[key($attr)]; break;
	}
		$sql="UPDATE product SET ".key($attr)."={$strval} WHERE id='{$id}'";
	
	$result = mysqli_query($cid, $sql);
	if(!$result) {
		$res['error']=mysqli_error($cid);
		$res['status']='FAIL';
	} else if (mysqli_affected_rows($cid)>0) {
		$res['status']='OK';	
	} else {
		$res['status']='NONE';
	}
	mysqli_close($cid);
	return $res;
}

function delete_product($id=null) {
	global $cid;
	if(!empty($id))
		$sql="DELETE FROM product WHERE id='{$id}'";
	$result = mysqli_query($cid, $sql);
	if(!$result) {
		$res['error']=mysqli_error($cid);
		$res['status']='FAIL';
	} else if (mysqli_affected_rows($cid)<1) {
		$res['status']='NONE';
	} else {
		$res['number']=mysqli_affected_rows($cid);
		$res['status']='OK';
	}
	mysqli_close($cid);
	return $res;
}

function add_sale($info) {
	global $cid;
	$sql="SELECT * FROM product WHERE id='{$info['id']}'";
	if (($result = mysqli_query($cid, $sql)) && mysqli_affected_rows($cid)) {
		$row=mysqli_fetch_array($result);
		$total=strval($row['price'])*$info['qty'];
		if($total<50 && $info['payment']!='CASH')
			die("<span style='color:red'>Invalid Payment Method!!!</span>");
		$datetime=date("Y-m-d H:i:s");
		$sql="INSERT INTO sale(datetime,id,qty,amount,payment)
			VALUES ('{$datetime}','{$info['id']}',{$info['qty']},{$total},'{$info['payment']}')";
		$result = mysqli_query($cid, $sql);

		$new_qty=intval($row['qty'])-$info['qty'];
		$sql="UPDATE product SET qty={$new_qty} WHERE id='{$info['id']}'";
		$result = mysqli_query($cid, $sql);	
		if($result && mysqli_affected_rows($cid)>0)	{
			$trans['id']=$row['id'];
			$trans['name']=$row['name'];
			$trans['price']=number_format($row['price'],2);
			$trans['qty']=$info['qty'];
			$trans['total']=number_format($total,2);
			$res['data']=$trans;
			$res['status']='OK';
		} else {
			$res['error']=mysqli_error($cid);
			$res['status']='FAIL';
		}
	} else {
		$res['error']=mysqli_error($cid);
		$res['status']='FAIL';
	}
	mysqli_close($cid);
	return $res;
}

function get_sale($id=null) {
	global $cid;
	if(!empty($id))
		$sql="SELECT product.name,product.price,sale.* FROM sale,product WHERE sale.id='{$id}' AND sale.id=product.id";
	else
		$sql="SELECT product.name,product.price,sale.* FROM sale,product WHERE sale.id=product.id";
	$result = mysqli_query($cid, $sql);
	if(!$result) {
		$res['error']=mysqli_error($cid);
		$res['status']='FAIL';
	} else if (mysqli_affected_rows($cid)<1) {
		$res['status']='NONE';
	} else {
		$count=0;
		$row=mysqli_fetch_assoc($result);
		$res['id']=$row['id'];
		$res['name']=$row['name'];
		$sum_qty=0; $sum_amount=0;
		do {
			$trans['no']=$row['no'];
			$trans['datetime']=$row['datetime'];
			$trans['qty']=$row['qty'];
			$trans['amount']=number_format($row['amount'],2);
			$trans['payment']=$row['payment'];
			$data[]=$trans;
			$sum_qty+=$row['qty'];
			$sum_amount+=$row['amount'];
			$count++;
		} while($row=mysqli_fetch_assoc($result));
		$res['total_qty']=number_format($sum_qty);
		$res['total_amount']=number_format($sum_amount,2);
		$res['number']=$count;
		$res['data']=$data;
		$res['status']='OK';
	}
	mysqli_close($cid);
	return $res;
}

?>
