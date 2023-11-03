<?php
/*
 * This is an KISS (Keep It Simple and Stupid) web monitor for
 * printrun / pronterface written in PHP.
 * Copyright (C) 2016  Jaroslav Škarvada <jskarvad@redhat.com>
 * The code is copied under GPLv3+. Use it on your own risk.
 */

require("config.php");

function format_time($t)
{
  return gmdate("H:i:s", intval($t));
}

function print_eta($f, $arr, $progress)
{
  if ($arr === NULL)
  {
    fprintf($f, "Not printing");
  } elseif (count($arr) == 3)
  {
    fprintf($f, "%s / %s, ", format_time($arr[0]), format_time($arr[1]));
    // $arr[2] (i.e. progress) seems to be polymorphic
    // queue index is reported if the type is integer
    if (is_integer($arr[2]))
    {
      fprintf($f, "idx: %d", intval($arr[2]));
      $progress = floatval($progress);
      if ($progress > 0)
        fprintf($f, " / %d", intval(round($arr[2] * 100 / $progress)));
    }
    // progress in percentage is reported if the type is double
    // (probably dupe of progress reported by different progress field)
    elseif (is_double($arr[2]))
      fprintf($f, "%5.2f %%", floatval($arr[2]) * 100);
    // report it as is in case of API change
    else
      fprintf($f, "%s", $arr[2]);
  }
}

function query_pronterface($f, $host, $port, $header)
{
  $socket = @fsockopen($host, $port, $errno, $errstr);

  if (!$socket)
  {
    if ($errno == 111)
      return 0;
    else
      fprintf($f, "$s\n", "<p>Socket error: $errno - $errstr</p>");
    return -1;
  }

  fputs($socket, $header);

  $data = "";
  while (!feof($socket))
    $data .= fgets($socket, 4096);

  fclose($socket);
  $xml = substr($data, strpos($data, "\r\n\r\n") + 4);
  $response = xmlrpc_decode($xml);

  if ($response && xmlrpc_is_fault($response))
    fprintf($f, "%s\n", "<p>xmlrpc: $response[faultString] ($response[faultCode])</p>");
  else
  {
    fprintf($f, "<table class='printer'>");
    fprintf($f, "<tr><th>Progress</th><td><big>%6.2f%%</big></td>", $response["progress"]);
    if (array_key_exists("T0", $response["temps"]))
      $temp = $response["temps"]["T0"];
    else
      $temp = $response["temps"]["T"];
    fprintf($f, "<th>Extruder temp</th><td><big><b>%.2f</b> ➡ %.2f</big></td></tr>", $temp[0], $temp[1]);
    fprintf($f, "<tr><th>Z</th><td>%s</td>", print_r($response["z"], true));
    fprintf($f, "<th>Bed temp</th><td><big><b>%.2f</b> ➡ %.2f</big></td></tr>", $response["temps"]["B"][0], $response["temps"]["B"][1]);
    fprintf($f, "<tr><th>Filename</th><td colspan='3' class='filename'>%s</td></tr>", print_r($response["filename"], true));
    fprintf($f, "<tr><th>ETA</th><td colspan='3'>");
    print_eta($f, $response["eta"], $response["progress"]);
    fprintf($f, "</td></tr>");
    fprintf($f, "</table>\n");
  }
  return 1;
}

function insert_status($host, $start_port, $delay, $status_cache)
{
  global $status_cache_lock, $printers_max, $path;

  $fp = fopen($status_cache_lock, "w");
  if (!file_exists($status_cache) || (time() - filemtime($status_cache) > $delay))
  {
    if (flock($fp, LOCK_EX))
    {
      if (!file_exists($status_cache) || (time() - filemtime($status_cache) > $delay))
      {
        $request = xmlrpc_encode_request("status", array());
        $contentlength = strlen($request);
        $reqheader = "POST $path HTTP/1.1\r\n" .
          "Host: $host\n" . "User-Agent: PHP query\r\n" .
          "Content-Type: text/xml\r\n".
          "Content-Length: $contentlength\r\n\r\n".
          "$request\r\n";
        $status = array();
        $port = $start_port;
        $stat = -1;
        $f = fopen($status_cache . ".tmp", "w");
        while ($port - $start_port < $printers_max && ($stat = query_pronterface($f, $host, $port, $reqheader)) != 0)
          $port++;
        if ($port == $start_port)
          fprintf($f, "%s\n", "<div class='error'>Unable to connect to Pronterface, maybe it is not running.</div>");
        fclose($f);
        rename($status_cache . ".tmp", $status_cache);
      }
      flock($fp, LOCK_UN);
    }
  }
  if (flock($fp, LOCK_SH))
  {
    $f = fopen($status_cache, "r");
    while (($buf = fgets($f, 4096)) !== false)
      echo($buf);
    fclose($f);
    flock($fp, LOCK_UN);
  }
  fclose($fp);
}

insert_status($host, $def_port, $cache_delay, $status_cache);
