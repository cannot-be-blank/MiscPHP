<?php
/**
 * generates universally unique ID
 * 
 * @return string a string of random characters to be used as a universally unique ID
 */
function uuid()
{
   return sprintf(
      '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
      // 32 bits for the time_low
      mt_rand(0, 0xffff), mt_rand(0, 0xffff),
      // 16 bits for the time_mid
      mt_rand(0, 0xffff),
      // 16 bits for the time_hi,
      mt_rand(0, 0x0fff) | 0x4000,
      // 8 bits and 16 bits for the clk_seq_hi_res,
      // 8 bits for the clk_seq_low,
      mt_rand(0, 0x3fff) | 0x8000,
      // 48 bits for the node
      mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
  );
}

/**
 * redirects to login page if user is not logged in
 */
function requireLogin()
{
   if(!isset($_SESSION['employeeID']))
   {
      header('Location: /sitepath/login.php');
      exit();
   }
}

/**
 * checks if a user has the correct permission to view a page, and does not allow them to if not
 * @param string $permimssionAuthorized the permission a user needs to view the page this function is called. current permissions this function can check are:
 * - `'system'`
 * - `'user'`
 * - `'sales'`
 * - `'accounting'`
 * @param AssocArray $user 
 */
function authorize($permissionAuthorized, $user)
{
   if($permissionAuthorized = 'system')
   {
      if(!$user['permission_system_functions'])
      {
         echo '<h1>You are not authroized to view this page</h1>';
         die();
      }
   }
   if($permissionAuthorized = 'user')
   {
      if(!$user['permission_user_functions'])
      {
         echo '<h1>You are not authroized to view this page</h1>';
         die();
      }
   }
   if($permissionAuthorized = 'sales')
   {
      if(!$user['permission_sales_functions'])
      {
         echo '<h1>You are not authroized to view this page</h1>';
         die();
      }
   }
   if($permissionAuthorized = 'accounting')
   {
      if(!$user['permission_accounting_functions'])
      {
         echo '<h1>You are not authroized to view this page</h1>';
         die();
      }
   }
}
/**
 * writes a caught error into a string containing:
 * - `date/time` (according to server) when the error was caught
 * - `file` and `line` the error was caught in
 * - `debug_backtrace()` of the error
 * 
 * @param string $eMessage an error message (typically an Exception's `getMessage()`)
 * @param __FILE__ $file the file the error was reported in (ALWAYS PASS `__FILE__` - it cannot be set by the function, as that would retrieve the file where this function lives)
 * @param __LINE__ $line the line the error was reported on (ALWAYS PASS `__LINE__` - it cannot be set by the function, as that would retrieve the line of the file where this function lives)
 * @return string a detailed error message
 */
function caught_error($eMessage, $file, $line)
{
   return date('m/d/Y H:i:s') . " - $file LINE $line REPORTED: $eMessage \n" . json_encode(debug_backtrace(), JSON_PRETTY_PRINT);
}

/**
 * formats a phone number with a specified format to phone number format: `###-###-####`
 * @param string $phone the phone number to format
 * @param regExp $regExp_originalFormat regular expression that matches the format of the passed phone number. Default format is `##########`
 * @return string phone number formatted as `###-###-####`
 */
function formatPhone($phone, $regExp_originalFormat = "/^([0-9]{3})([0-9]{3})([0-9]{4})$/")
{
   if(preg_match($regExp_originalFormat, $phone, $value))
   {
      $formattedPhone = $value[1].'-'.$value[2].'-'.$value[3];
      return $formattedPhone;
   }
   else return 'INVALID PHONE NUMBER';
}

/**
 * converts degrees minutes and seconds of a latitude or longitude to decimal coordinate format
 */
function DMStoDecimalCoordinates($direction, $degrees, $minutes, $seconds)
{
    if($direction == 'N' || $direction == 'E'){ return $degrees+((($minutes*60)+($seconds))/3600); }
    else return -($degrees+((($minutes*60)+($seconds))/3600));
}

/**
 * saves the contents of an array to a csv file 
 * 
 * @param string $fileName the name the saved file will have
 * @param array $array the array to write to csv
 * @param string $filePath [optional] path to put the file if not same directory (ex. `'OneDirectoryDeeper/AnotherDirectoryDeeper/'`)
 */
function saveArrayToCSV($fileName, $array, $filePath = '')
{
    $file = fopen($filePath.$fileName, 'w');
    foreach($array as $row)
    {
        fputcsv($file, $row);
    }
    fclose($file);
}

/**
 * saves the contents of a sql select result to a csv file 
 * 
 * @param string $fileName the name the saved file will have
 * @param array $resultRows the sql result to write to csv
 * @param string $filePath [optional] path to put the file if not same directory (ex. `'OneDirectoryDeeper/AnotherDirectoryDeeper/'`)
 */
function sqlResultToCSV($fileName, $resultRows, $filePath = '')
{
    $file = fopen($filePath.$fileName, 'w');
    fputcsv($file, array_keys($resultRows[0]));
    foreach($resultRows as $resultRow)
    {
        fputcsv($file, $resultRow);
    }
    fclose($file);
}
?>