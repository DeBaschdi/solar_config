#!/usr/bin/php
<?php
/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2015-2020]  [Ulrich Kunz]
//
//  Dieses Programm ist freie Software. Sie können es unter den Bedingungen
//  der GNU General Public License, wie von der Free Software Foundation
//  veröffentlicht, weitergeben und/oder modifizieren, entweder gemäß
//  Version 3 der Lizenz oder (nach Ihrer Option) jeder späteren Version.
//
//  Die Veröffentlichung dieses Programms erfolgt in der Hoffnung, daß es
//  Ihnen von Nutzen sein wird, aber OHNE IRGENDEINE GARANTIE, sogar ohne
//  die implizite Garantie der MARKTREIFE oder der VERWENDBARKEIT FÜR EINEN
//  BESTIMMTEN ZWECK. Details finden Sie in der GNU General Public License.
//
//  Ein original Exemplar der GNU General Public License finden Sie hier:
//  http://www.gnu.org/licenses/
//
//  Dies ist ein Programmteil des Programms "Solaranzeige"
//
//  Es dient dem Auslesen des eSmart3 MPPT Reglers über die USB Schnittstelle
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//  Achtung! Der Regler sendet zwischendurch immer wieder asynchrone Daten!
//
*****************************************************************************/
$path_parts = pathinfo($argv[0]);
$Pfad = $path_parts['dirname'];
if (!is_file($Pfad."/1.user.config.php")) {
  // Handelt es sich um ein Multi Regler System?
  require($Pfad."/user.config.php");
}

require_once($Pfad."/phpinc/funktionen.inc.php");
if (!isset($funktionen)) {
  $funktionen = new funktionen();
}
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}

$Tracelevel = 7;  //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Device = "WR"; // WR = Wechselrichter
$Version = "";
$aktuelleDaten = array(
    "Firmware" => 0,
    "Produkt" => 0,
    "Geraetestatus" => 0,
    "Solarspannung" => 0, 
    "Solarstrom" => 0,
    "Solarleistung" => 0,
    "Temperatur" => 0
    );
$Start = time();  // Timestamp festhalten
$funktionen->log_schreiben("---------   Start  eSmart3.php    ---------------------------- ","|--",6);

$funktionen->log_schreiben("Zentraler Timestamp: ".$zentralerTimestamp,"   ",8);
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;

setlocale(LC_TIME,"de_DE.utf8");


//  Hardware Version ermitteln.
$Teile =  explode(" ",$Platine);
if ($Teile[1] == "Pi") {
  $Version = trim($Teile[2]);
  if ($Teile[3] == "Model") {
    $Version .= trim($Teile[4]);
    if ($Teile[5] == "Plus") {
      $Version .= trim($Teile[5]);
    }
  }
}
$funktionen->log_schreiben("Hardware Version: ".$Version,"o  ",9);

switch($Version) {
  case "2B":
  break;
  case "3B":
  break;
  case "3BPlus":
  break;
  case "4B":
  break;
  default:
  break;
}




/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
//  pro Tag zu speichern.
//  Achtung! Dieser Wert wird jeden Tag um Mitternacht auf 0 gesetzt.
//
*****************************************************************************/


if ($HF2211) {
  // HF2211 WLAN Gateway wird benutzt
  $USB1 = fsockopen($WR_IP, $WR_Port, $errno, $errstr, 5);   // 5 Sekunden Timeout
  if ($USB1 === false) {
    $funktionen->log_schreiben("Kein Kontakt zum Wechselrichter ".$WR_IP."  Port: ".$WR_Port,"XX ",3);
    $funktionen->log_schreiben("Exit.... ","XX ",3);
    goto Ausgang;
  }
}
else {
  $USB1 = $funktionen->openUSB($USBRegler);
  if (!is_resource($USB1)) {
    $funktionen->log_schreiben("USB Port kann nicht geöffnet werden. [1]","XX ",7);
    $funktionen->log_schreiben("Exit.... ","XX ",7);
    goto Ausgang;
  }
}
$funktionen->log_schreiben("USB Port: ".$USBRegler,"   ",7);


$StartByte = "AA"; 
$DeviceType = "01";   // MPPT Controler
$Adresse = "00";      // 00 = Broadcast Adresse. Nachricht an alle



if (1==1) {

  $i = 1;
  do {
    $funktionen->log_schreiben("Die Daten werden ausgelesen...","+  ",9);

    /**************************************************************************
    //  Ab hier wird der Regler ausgelesen.
    //
    //  Ergebniswerte:
    //  $aktuelleDaten["Firmware"]
    //  $aktuelleDaten["Produkt"]
    //  $aktuelleDaten["Batteriespannung"]
    //  $aktuelleDaten["Solarstrom"]
    //  $aktuelleDaten["Solarspannung"]
    //  $aktuelleDaten["Verbraucherstrom"]
    //  $aktuelleDaten["KilowattstundenGesamt"]
    //  $aktuelleDaten["Temperatur"]
    //
    **************************************************************************/



    $CommandID = "01";    // Get some Data
    $DataItem = "00";     // 00 = Real time Data
    $Datenlaenge = "03";   // in HEX
    $Daten = "00001E";

    $Datenlaenge = str_pad ( dechex((strlen($Daten)/2)), 2, '0', STR_PAD_LEFT );
    $Datenstring = $StartByte.$DeviceType.$Adresse.$CommandID.$DataItem.$Datenlaenge.$Daten;
    $CRC = $funktionen->eSmart3_CRC($Datenstring);


    $rc = $funktionen->eSmart3_auslesen($USB1,$Datenstring.$CRC);
    if ($rc === false) {
      $funktionen->log_schreiben("Datenfehler, nochmal... ".$Datenstring,"!! ",7);
      continue;
    }
    $aktuelleDaten = array_merge($aktuelleDaten, $funktionen->eSmart3_ergebnis_auswerten($rc));
    $funktionen->log_schreiben($rc,"   ",8);



    $CommandID = "01";    // Get some Data
    $DataItem = "01";     // 00 = Real time Data
    $Datenlaenge = "03";   // in HEX
    $Daten = "000014";

    $Datenlaenge = str_pad ( dechex((strlen($Daten)/2)), 2, '0', STR_PAD_LEFT );
    $Datenstring = $StartByte.$DeviceType.$Adresse.$CommandID.$DataItem.$Datenlaenge.$Daten;
    $CRC = $funktionen->eSmart3_CRC($Datenstring);


    $rc = $funktionen->eSmart3_auslesen($USB1,$Datenstring.$CRC);
    if ($rc === false) {
      $funktionen->log_schreiben("Datenfehler, nochmal... ".$Datenstring,"!! ",7);
      continue;
    }
    $funktionen->log_schreiben($rc,"   ",8);

    $aktuelleDaten = array_merge($aktuelleDaten, $funktionen->eSmart3_ergebnis_auswerten($rc));




    $CommandID = "01";    // Get some Data
    $DataItem = "02";     // 00 = Real time Data
    $Datenlaenge = "03";   // in HEX
    $Daten = "000032";

    $Datenlaenge = str_pad ( dechex((strlen($Daten)/2)), 2, '0', STR_PAD_LEFT );
    $Datenstring = $StartByte.$DeviceType.$Adresse.$CommandID.$DataItem.$Datenlaenge.$Daten;
    $CRC = $funktionen->eSmart3_CRC($Datenstring);


    $rc = $funktionen->eSmart3_auslesen($USB1,$Datenstring.$CRC);
    if ($rc === false) {
      $funktionen->log_schreiben("Datenfehler, nochmal... ".$Datenstring,"!! ",7);
      continue;
    }
    $funktionen->log_schreiben($rc,"   ",8);

    $aktuelleDaten = array_merge($aktuelleDaten, $funktionen->eSmart3_ergebnis_auswerten($rc));





    $CommandID = "01";    // Get some Data
    $DataItem = "08";     // 00 = Real time Data
    $Datenlaenge = "03";   // in HEX
    $Daten = "000020";

    $Datenlaenge = str_pad ( dechex((strlen($Daten)/2)), 2, '0', STR_PAD_LEFT );
    $Datenstring = $StartByte.$DeviceType.$Adresse.$CommandID.$DataItem.$Datenlaenge.$Daten;
    $CRC = $funktionen->eSmart3_CRC($Datenstring);


    $rc = $funktionen->eSmart3_auslesen($USB1,$Datenstring.$CRC);
    if ($rc === false) {
      $funktionen->log_schreiben("Datenfehler, nochmal... ".$Datenstring,"!! ",7);
      continue;
    }
    $funktionen->log_schreiben($rc,"   ",8);

    $aktuelleDaten = array_merge($aktuelleDaten, $funktionen->eSmart3_ergebnis_auswerten($rc));



    /****************************************************************************
    //  Die Daten werden für die Speicherung vorbereitet.
    ****************************************************************************/
    $aktuelleDaten["Regler"] = $Regler;
    $aktuelleDaten["Objekt"] = $Objekt;
    $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);


    if ($i == 1) 
      $funktionen->log_schreiben(var_export($aktuelleDaten,1),"   ",8);


    /**************************************************************************
    //  User PHP Script, falls gewünscht oder nötig
    **************************************************************************/
    if ( file_exists ("/var/www/html/eSmart3_math.php")) {
      include 'eSmart3_math.php';  // Falls etwas neu berechnet werden muss.
    }


    /**************************************************************************
    //
    //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
    //  an den mqtt-Broker Mosquitto gesendet.
    **************************************************************************/
    if ($MQTT and $i == 1) {
      $funktionen->log_schreiben("MQTT Daten zum [ $MQTTBroker ] senden.","   ",1);
      require($Pfad."/mqtt_senden.php");
    }


    /**************************************************************************
    //  Zeit und Datum
    **************************************************************************/
    //  Der Regler hat keine interne Uhr! Deshalb werden die Daten vom Raspberry benutzt.
    $aktuelleDaten["Timestamp"] = time();
    $aktuelleDaten["Monat"]     = date("n");
    $aktuelleDaten["Woche"]     = date("W");
    $aktuelleDaten["Wochentag"] = strftime("%A",time());
    $aktuelleDaten["Datum"]     = date("d.m.Y");
    $aktuelleDaten["Uhrzeit"]      = date("H:i:s");



    /**************************************************************************
    //  InfluxDB  Zugangsdaten ...stehen in der user.config.php
    //  falls nicht, sind das hier die default Werte.
    **************************************************************************/
    $aktuelleDaten["InfluxAdresse"] = $InfluxAdresse;
    $aktuelleDaten["InfluxPort"] =  $InfluxPort;
    $aktuelleDaten["InfluxUser"] =  $InfluxUser;
    $aktuelleDaten["InfluxPassword"] = $InfluxPassword;
    $aktuelleDaten["InfluxDBName"] = $InfluxDBName;
    $aktuelleDaten["InfluxDaylight"] = $InfluxDaylight;
    $aktuelleDaten["InfluxDBLokal"] = $InfluxDBLokal;
    $aktuelleDaten["InfluxSSL"] = $InfluxSSL;
    $aktuelleDaten["Demodaten"] = false;



    /*********************************************************************
    //  Daten werden in die Influx Datenbank gespeichert.
    //  Lokal und Remote bei Bedarf.
    *********************************************************************/
    if ($InfluxDB_remote) {
      // Test ob die Remote Verbindung zur Verfügung steht.
      if ($RemoteDaten) {
        $rc = $funktionen->influx_remote_test();
        if ($rc) {
          $rc = $funktionen->influx_remote($aktuelleDaten);
          if ($rc) {
            $RemoteDaten = false;
          }
        }
        else {
          $RemoteDaten = false;
        }
      }
      if ($InfluxDB_local) {
        $rc = $funktionen->influx_local($aktuelleDaten);
      }
    }
    else {
      $rc = $funktionen->influx_local($aktuelleDaten);
    }


    if (is_file($Pfad."/1.user.config.php")) {
      // Ausgang Multi-Regler-Version
      $Zeitspanne = (7 - (time() - $Start));
      $funktionen->log_schreiben("Multi-Regler-Ausgang. ".$Zeitspanne,"   ",2);
      if ($Zeitspanne > 0) {
        sleep($Zeitspanne);
      }
      break;
    }
    else {
      $funktionen->log_schreiben("Schleife: ".($i)." Zeitspanne: ".(floor((56 - (time() - $Start))/($Wiederholungen-$i+1))),"   ",9);
      sleep(floor((56 - (time() - $Start))/($Wiederholungen-$i+1)));
    }
    if ($Wiederholungen <= $i or $i >= 6) {
      $funktionen->log_schreiben("OK. Daten gelesen.","   ",8);
      $funktionen->log_schreiben("Schleife ".$i." Ausgang...","   ",8);
      break;
    }

    $i++;
  } while (($Start + 20) > time());
}


if (isset($aktuelleDaten["Firmware"]) and isset($aktuelleDaten["Regler"])) {


  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
    $funktionen->log_schreiben("Daten werden zur HomeMatic übertragen...","   ",8);
    require($Pfad."/homematic.php");
  }

  /*********************************************************************
  //  Sollen Nachrichten an einen Messenger gesendet werden?
  //  Bei einer Multi-Regler-Version sollte diese Funktion nur bei einem
  //  Gerät aktiviert sein.
  *********************************************************************/
  if (isset($Messenger) and $Messenger == true) {
    $funktionen->log_schreiben("Nachrichten versenden...","   ",8);
    require($Pfad."/meldungen_senden.php");
  }

  $funktionen->log_schreiben("OK. Datenübertragung erfolgreich.","   ",7);

}
else {
  $funktionen->log_schreiben("Keine gültigen Daten empfangen.","!! ",6);
}



Ausgang:

$funktionen->log_schreiben("---------   Stop   eSmart3.php    ---------------------------- ","|--",6);

return;



?>