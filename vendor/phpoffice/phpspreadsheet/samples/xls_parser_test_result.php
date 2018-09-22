<?php

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Helper\Sample;

require_once __DIR__ . '/../src/Bootstrap.php';

$helper = new Sample();

// Return to the caller script when runs by CLI
if ($helper->isCli()) {
    return;
}

$inputFileName = $_FILES["fileToUpload"]["name"];
$tmpFilename   = $_FILES['fileToUpload']['tmp_name'];
$supplier      = $_POST["supplier"];

$invoice_num;
$invoice_dt;
$ship_to;
$arr = [];
$items = [];
$table = '';

function reassign_array_values($item, $key)
{
	global $arr;
	if(!empty($item)) {
		$arr[] = $item;
	}

}
/*
* updated 2018-09-22 kypark
*/
function getTotalAmountOfHairzoneInvoice($footer)
{
	$total = 0;
	$pattern = '/SUBTOTAL\s+\n&"-,Regular"[0-9]+\.[0-9]+\s+\n&"-,Regular"[0-9]+\.[0-9]+/';
	preg_match($pattern, $footer, $matches);
	if(!empty($matches[0])){
		$tmp_str = preg_replace('/(SUBTOTAL|&"-,Regular"|\n)/', '', $matches[0]);
		$tmp_arr = preg_split('/\s/', $str);
		//var_dump($arr);
		$total = $tmp_arr[1] - $tmp_arr[2];
	}
	return $total;
}

try {

	$spreadsheet = IOFactory::load($tmpFilename);
	$sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

	//get footer to calc total amount 
	$footer = $spreadsheet->getActiveSheet()->getHeaderFooter()->getOddFooter();
	$total = getTotalAmountOfHairzoneInvoice($footer);
	

	if (is_array($sheetData)) {
		array_walk_recursive($sheetData, 'reassign_array_values');

		$invoice_num = ($arr[0] == "INVOICE# :" && intval($arr[1]) > 0) 
					 ? $arr[1] 
					 : ''
					 ;

		$invoice_dt = ($arr[9] == "INVOICE DATE :" && !empty($arr[8])) 
					 ? $arr[8] 
					 : ''
					 ;

		$ship_to = ($arr[13] == "SHIP TO" && !empty($arr[14])) 
				 ? $arr[14] 
				 : ''
				 ;
		
		$table = "<table border=1>"	
			   . "<tr><th>upc</th><th>qty</th><th>description</th><th>color</th></tr>"
			   ;

		$fileNm = $invoice_num . '-' . $invoice_dt . '.csv';

	    $file = fopen($tmpFilename.'.csv', 'w');
 
		// save the column headers
		fputcsv($file, array('upc', 'qty', 'description', 'color'));

		$data = array();

		foreach ($arr as $key => $value) {

			if($key < 38) continue;

			if(in_array(fmod($key,10), array(3,8)) && strlen($value) > 20) {

				$tmp = preg_split('/\n/', $value);
				$tmp_nm = $tmp[0];
				$tmp_items = $tmp[1];

				$pattern = '/[^\s]+\s\[[0-9]+\]\s+[^\s]+/';

				preg_match_all($pattern, $tmp_items, $matches);

				foreach ($matches[0] as $i => $item) {

					$aPos = strpos($item, "[");
					$bPos = strpos($item, "]");

					$tmp_color = substr($item, 0, $aPos);
					$tmp_upc   = substr($item, $aPos+1, $bPos-$aPos-1);
					$tmp_qty   = substr($item, $bPos+3 );

					$table .= '<tr>';
					$table .= '<td>' . $tmp_upc . '</td>';
					$table .= '<td>' . $tmp_qty . '</td>';
					$table .= '<td>' . $tmp_nm . '</td>';
					$table .= '<td>' . $tmp_color . '</td>';
					$table .= '</tr>';

					$data[] = array($tmp_upc
									, $tmp_qty
									, $tmp_nm
									, $tmp_color
							  );
				}
			}
		}

		// save each row of the data
		foreach ($data as $row)
		{
			fputcsv($file, $row);
		}		 
		// Close the fil
		fclose($file);

		$table .= '</table>';
		echo $table;

	}

	echo "</pre>";

} catch (InvalidArgumentException $e) {
    $helper->log('Error loading file "' . pathinfo($tmpFilename, PATHINFO_BASENAME) . '": ' . $e->getMessage());
}
