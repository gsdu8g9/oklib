<?php

/**
* Функции для работы с CSV
*\file csv_utils.php
*/

/**
* Чтение CSV файла в массив вида array(array("столбец1" => значение1, ..), ...)
*@param $name имя файла
*@param $condition условие для строки, функция применяется ко всем строкам, возвращает добавлять или нет строку в результат
*@param возвращать ли значения
*@return возвращает массив из ассоциативных масивов
*/

function cs_throw($text)
{
	throw new Exception("[THR][".time()."] ".$text."\n");
}

function read_csv_condition($name, $condition, $return_result = TRUE)
{
	$result = array();
	$file = fopen($name, "r");
	if($file == FALSE)
	{
		cs_throw("Couldn't open $name\n");
	}

	debug("Loaded $name");

	$header = fgetcsv($file, 0, ';', '"');
	if($header === FALSE)
	{
		cs_throw("Empty csv $name\n");
	}	

	$line = 1;	
	while(($data = fgetcsv($file, 0, ';', '"')) !== FALSE)
	{
		if(count($data) != count($header))
		{
			cs_throw("Wrong csv, line $line has ". count($data) . " columns");
		}
		$temp = array();
		$counter = 0;
		foreach($data as $d)
		{
			$temp[$header[$counter]] = $d;
			$counter++;
		}
		
		if($condition($temp))
		{
			if($return_result)
			{
				$result[] = $temp;
			}
		}
		$line++;
	}

	fclose($file);

	if($return_result)
	{
		return $result;
	}
}

/**
*Применить функцию ко всем элементам CSV
*@param $name имя файла
*@param $func функция которая применяется ко всем строкам CSV
*/
function for_each_csv_line($name, $func)
{
	return read_csv_condition($name, $func, FALSE);
}

///прочитать в csv в массив
///@see read_csv_condition
function read_csv($name)
{
	return read_csv_condition($name, function($r){ return TRUE; } );
}

/**
*Записать CSV
*@param $name имя файла
*@param $data ассоциативный массив который записывается
*@param $header массив заголовок
*/
function write_csv($name, $data, $header)
{
	$result = array();
	$file = fopen($name, "w");
	if($file == FALSE)
	{
		cs_throw("Couldn't open $name\n");
	}
	
	fputcsv($file, $header, ';', '"');
	
	foreach($data as $d)
	{
		$temp = array();
		foreach($header as $h)
		{
			if(!array_key_exists($h, $d))
			{
				print_r($d);
				print_r($data);
				cs_throw("Value $h is not specified ");
			}
			$temp[] = $d[$h];
		}	
		
		fputcsv($file, $temp, ';', '"');
	}

	fclose($file);
}

?>
