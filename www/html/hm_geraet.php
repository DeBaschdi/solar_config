#!/usr/bin/php
<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2016 - 2022] [Ulrich Kunz]
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
//  Es dient dem Auslesen von Geräten, angeschlossen an die HomeMatic
//  Das Gerät wird wie ein Wechselrichter konfiguriert. Bitte in der
//  user.config.php die IP Adresse der HomeMatic und den Port = 80 eintragen.
//
//  Die Geräte, die ausgelesen werden sollen, werden in der user.config.php
//  eingetragen.
//
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
*****************************************************************************
$aktuelleDaten = array();
$HM_Geraetetyp = array();
$HM_Seriennummer = array();

$HM_Geraetetyp[1] = "HM-CC-RT-DN";     // Heizungsthermostat
$HM_Seriennummer[1] = "OEQ2419985";    // Wohnzimmer

$HM_Geraetetyp[2] = "HmIP-eTRV-B";     // Heizungsthermostat
$HM_Seriennummer[2] = "00201D89A8A446";// Badezimmer

$HM_Geraetetyp[3] = "HmIP-STHD";       // Wandthermostat
$HM_Seriennummer[3] = "000E9BE9967967";// Badezimmer

$HM_Geraetetyp[4] = "HM-CC-RT-DN";     // Heizungsthermostat
$HM_Seriennummer[4] = "OEQ2421488";    // Küche

****************************************************************************/
$path_parts = pathinfo( $argv[0] );
$Pfad = $path_parts['dirname'];
if (!is_file( $Pfad."/1.user.config.php" )) {
  // Handelt es sich um ein Multi Regler System?
  require ($Pfad."/user.config.php");
}
require_once ($Pfad."/phpinc/funktionen.inc.php");
if (!isset($funktionen)) {
  $funktionen = new funktionen( );
}
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}
$Tracelevel = 7; //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Device = "HM"; // HM = HomeMatic
$Start = time( ); // Timestamp festhalten
$Version = "";
$funktionen->log_schreiben( "-------------   Start  hm_geraet.php    -------------------------- ", "|--", 6 );
$funktionen->log_schreiben( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten["WattstundenGesamtHeute"] = 0;
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
setlocale( LC_TIME, "de_DE.utf8" );
//  Hardware Version ermitteln.
$Teile = explode( " ", $Platine );
if ($Teile[1] == "Pi") {
  $funktionen->log_schreiben( "Hardware Version: ".$Platine, "o  ", 8 );
  $Version = trim( $Teile[2] );
  if ($Teile[3] == "Model") {
    $Version .= trim( $Teile[4] );
    if ($Teile[5] == "Plus") {
      $Version .= trim( $Teile[5] );
    }
  }
}
switch ($Version) {

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

/************************************************************
//  Prüfen ob dir Homematic Zentrale erreichbar ist.
//
************************************************************/
$rCurlHandle = curl_init( "http://".$WR_IP );
curl_setopt( $rCurlHandle, CURLOPT_CONNECTTIMEOUT, 10 );
curl_setopt( $rCurlHandle, CURLOPT_HEADER, TRUE );
curl_setopt( $rCurlHandle, CURLOPT_NOBODY, TRUE );
curl_setopt( $rCurlHandle, CURLOPT_RETURNTRANSFER, TRUE );
$strResponse = curl_exec( $rCurlHandle );
$Connect = curl_errno( $rCurlHandle);
$funktionen->log_schreiben(print_r(curl_getinfo( $rCurlHandle ),1), "1  " , 9 );
curl_close( $rCurlHandle );
if ($Connect == 0) {
  //  Verbindung ist OK
  $HM_Verbindung = true;
  $funktionen->log_schreiben( "Verbindung zur Homematic Zentrale besteht. IP: ".$WR_IP, "   ", 8 );
}
else {
  $funktionen->log_schreiben( "Keine Verbindung zur Homematic Zentrale! IP: ".$WR_IP." Fehlernummer: [ ".$Connect." ]", "   ", 4 );
  $HM_Verbindung = false;
  goto Ausgang;
}
$i = 1;
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
do {
  $funktionen->log_schreiben( "Die Daten werden ausgelesen...", "+  ", 7 );

  /****************************************************************************
  //  Ab hier wird die HomeMatic ausgelesen.
  //  Es können mehrere Geräte hintereinander ausgelesen werden.
  //  Das Auslesen aller Geräte darf nicht länger als 9 Sekunden dauern.
  ****************************************************************************/
  if ($HM_Verbindung) {
    for ($s = 1; $s <= count( $HM_Seriennummer ); $s++) {

      /************************************************************
      //  Geräte auslesen.
      //
      ************************************************************/
      $rCurlHandle = curl_init( "http://".$WR_IP."/config/xmlapi/devicelist.cgi" );
      curl_setopt( $rCurlHandle, CURLOPT_CUSTOMREQUEST, "GET" );
      curl_setopt( $rCurlHandle, CURLOPT_TIMEOUT, 20 );
      curl_setopt( $rCurlHandle, CURLOPT_PORT, 80 );
      curl_setopt( $rCurlHandle, CURLOPT_RETURNTRANSFER, TRUE );
      $strResponse = curl_exec( $rCurlHandle );
      $rc_info = curl_getinfo( $rCurlHandle );
      if (curl_errno( $rCurlHandle )) {
        $funktionen->log_schreiben( "Curl Fehler! HomeMatic wurde nicht gelesen! No. ".curl_errno( $ch ), "   ", 5 );
      }
      if ($rc_info["http_code"] == 200 or $rc_info["http_code"] == 204) {
        $funktionen->log_schreiben( "HomeMatic Daten gelesen. ", "*  ", 8 );
      }
      $funktionen->log_schreiben( "Gerät ".$s." => ".$HM_Seriennummer[$s], "*  ", 8 );
      $xml = XMLReader::xml( $strResponse );
      while ($xml->name !== 'deviceList') {
        $xml->read( );
      }
      $dom = $xml->expand( new DOMDocument( ));
      $doc = (array) simplexml_import_dom( $dom );
      for ($i = 0; $i < count( $doc["device"] ); $i++) {
        if ((string) $doc["device"][$i]->attributes( ) ["device_type"] == $HM_Geraetetyp[$s]) {
          if ((string) $doc["device"][$i]->attributes( ) ["address"] == $HM_Seriennummer[$s]) {
            $aktuelleDaten[$HM_Seriennummer[$s]]["Device_ID"] = (string) $doc["device"][$i]->attributes( ) ["ise_id"];
            $aktuelleDaten[$HM_Seriennummer[$s]]["Seriennummer"] = (string) $doc["device"][$i]->attributes( ) ["address"];
            $aktuelleDaten[$HM_Seriennummer[$s]]["Typ"] = (string) $doc["device"][$i]->attributes( ) ["device_type"];
            $aktuelleDaten[$HM_Seriennummer[$s]]["Bezeichnung"] = (string) $doc["device"][$i]->attributes( ) ["name"];
            if ((string) $doc["device"][$i]->attributes( ) ["address"] == $HM_Seriennummer[$s]) {
              break;
            }
          }
        }
      }
      curl_close( $rCurlHandle );
      unset($xml);
      unset($dom);
      unset($doc);
      $aktuelleDaten["Measurement".$s] = $aktuelleDaten[$HM_Seriennummer[$s]]["Seriennummer"];
      $rCurlHandle = curl_init( "http://".$WR_IP."/config/xmlapi/state.cgi?device_id=".$aktuelleDaten[$HM_Seriennummer[$s]]["Device_ID"] );
      curl_setopt( $rCurlHandle, CURLOPT_CUSTOMREQUEST, "GET" );
      curl_setopt( $rCurlHandle, CURLOPT_TIMEOUT, 20 );
      curl_setopt( $rCurlHandle, CURLOPT_PORT, 80 );
      curl_setopt( $rCurlHandle, CURLOPT_RETURNTRANSFER, TRUE );
      $strResponse = curl_exec( $rCurlHandle );
      $rc_info = curl_getinfo( $rCurlHandle );
      if (curl_errno( $rCurlHandle )) {
        $funktionen->log_schreiben( "Curl Fehler! HomeMatic konnte nicht gelesen werden! No. ".curl_errno( $ch ), "   ", 5 );
      }
      if ($rc_info["http_code"] == 200 or $rc_info["http_code"] == 204) {
        $funktionen->log_schreiben( "HomeMatic Daten gelesen. ", "*  ", 8 );
      }
      $xml = XMLReader::xml( $strResponse );
      while ($xml->name !== 'state') {
        $xml->read( );
      }
      $dom = $xml->expand( new DOMDocument( ));
      $doc = (array) simplexml_import_dom( $dom );
      $funktionen->log_schreiben( print_r( (array) $doc["device"], 1 ), "   ", 8 );
      for ($i = 0; $i < count( $doc["device"] ); $i++) {
        for ($k = 0; $k < 30; $k++) {
          $funktionen->log_schreiben( $i." ".$k." ".(string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"], "   ", 8 );
          if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "ACTUAL_TEMPERATURE") {
            $aktuelleDaten["HM_Seriennummer".$s]["Temperatur"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
            $aktuelleDaten["HM_Seriennummer".$s]["Temperatur_Unit"] = "°C";
          }
          if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "WINDOW_STATE") {
            // 0 = geschlossen  10 = offen
            $aktuelleDaten["HM_Seriennummer".$s]["Fenster_offen"] = (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"] * 10;
          }
          if (substr( $HM_Geraetetyp[$s], 0, 4 ) == "HmIP") {
            // HomeMatic IP Geräte
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "LEVEL") {
              $aktuelleDaten["HM_Seriennummer".$s]["Ventil-Oeffnungsgrad"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"] * 100, 0 );
              $aktuelleDaten["HM_Seriennummer".$s]["Ventil_Unit"] = "%";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "OPERATING_VOLTAGE") {
              $aktuelleDaten["HM_Seriennummer".$s]["Batteriespannung"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["Batteriespannung_Unit"] = "V";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "HUMIDITY") {
              $aktuelleDaten["HM_Seriennummer".$s]["Luftfeuchte"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["Luftfeuchte_Unit"] = "%";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "CURRENT_ILLUMINATION") {
              $aktuelleDaten["HM_Seriennummer".$s]["Helligkeit_aktuell"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "AVERAGE_ILLUMINATION") {
              $aktuelleDaten["HM_Seriennummer".$s]["Helligkeit_durchschnitt"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
            }
          }
          elseif (substr( $HM_Geraetetyp[$s], 0, 8 ) == "HMIP-PSM") {
			if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "POWER") {
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Leistung"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Leistung_Unit"] = "W";
            }
			if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "VOLTAGE") {
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Spannung"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Spannung_Unit"] = "V";
            }
			if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "CURRENT") {
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Strom"] = (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"] / 1000;
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Strom_Unit"] = "A";
            }
			if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "FREQUENCY") {
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Frequenz"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Frequenz_Unit"] = "Hz";
            }
			if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "ENERGY_COUNTER") {
              $aktuelleDaten["HM_Seriennummer".$s]["WattstundenGesamt"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 3 );
              $aktuelleDaten["HM_Seriennummer".$s]["WattstundenGesamt_Unit"] = "Wh";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "STATE") {
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Status"] =  '"'.(string)$doc["device"][0]->channel[3]->datapoint[$k]["value"].'"';
            }
          }
          elseif ($HM_Geraetetyp[$s] == "HM-ES-TX-WM") {
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "GAS_ENERGY_COUNTER") {
              $aktuelleDaten["HM_Seriennummer".$s]["Kubikmeter_Gas"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["Gas_Unit"] = "m³";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "ENERGY_COUNTER") {
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Verbrauch"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"] * 100, 0 );
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Verbrauch_Unit"] = "Wh";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "POWER") {
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Verbrauch_Leistung"] = (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"] ;
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Verbrauch_Leistung_Unit"] = "W";
            }
          }
          else {
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "VALVE_STATE") {
              $aktuelleDaten["HM_Seriennummer".$s]["Ventil-Oeffnungsgrad"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 0 );
              $aktuelleDaten["HM_Seriennummer".$s]["Ventil_Unit"] = "%";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "BATTERY_STATE") {
              $aktuelleDaten["HM_Seriennummer".$s]["Batteriespannung"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["Batteriespannung_Unit"] = "V";
            }
          }
          if (empty((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"])) {
            break;
          }
        }
      }
      unset($xml);
      unset($dom);
      unset($doc);
    }
    $aktuelleDaten["Anzahl_Geraete"] = count( $HM_Seriennummer );
  }
  else {
    goto Ausgang;
  }

  /****************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/

  /****************************************************************************
  //  Ab hier werden Werte in die HomeMatic geschrieben.
  //  Es können mehrere Werte hintereinander geschrieben werden.
  //  Das Auslesen und Schreiben aller Werte darf nicht länger als 9 Sekunden
  //  dauern.
  ****************************************************************************/

  /****************************************************************************
  //  ENDE WERTE SCHREIBEN      ENDE WERTE SCHREIBEN      ENDE WERTE SCHREIBEN
  ****************************************************************************/

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["Produkt"] = "HomeMatic";
  $aktuelleDaten["Firmware"] = "unbekannt";
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
  $aktuelleDaten["WattstundenGesamtHeute"] = 0; // dummy
  $funktionen->log_schreiben( var_export( $aktuelleDaten, 1 ), "   ", 8 );

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists( "/var/www/html/hm_geraet_math.php" )) {
    include 'hm_geraet_math.php'; // Falls etwas neu berechnet werden muss.
  }

  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT and $i == 1) {
    $funktionen->log_schreiben( "MQTT Daten zum [ $MQTTBroker ] senden.", "   ", 1 );
    require ($Pfad."/mqtt_senden.php");
  }

  /****************************************************************************
  //  Zeit und Datum
  ****************************************************************************/
  $aktuelleDaten["Timestamp"] = time( );
  $aktuelleDaten["Monat"] = date( "n" );
  $aktuelleDaten["Woche"] = date( "W" );
  $aktuelleDaten["Wochentag"] = strftime( "%A", time( ));
  $aktuelleDaten["Datum"] = date( "d.m.Y" );
  $aktuelleDaten["Uhrzeit"] = date( "H:i:s" );

  /****************************************************************************
  //  InfluxDB  Zugangsdaten ...stehen in der user.config.php
  //  falls nicht, sind das hier die default Werte.
  ****************************************************************************/
  $aktuelleDaten["InfluxAdresse"] = $InfluxAdresse;
  $aktuelleDaten["InfluxPort"] = $InfluxPort;
  $aktuelleDaten["InfluxUser"] = $InfluxUser;
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
      $rc = $funktionen->influx_remote_test( );
      if ($rc) {
        $rc = $funktionen->influx_remote( $aktuelleDaten );
        if ($rc) {
          $RemoteDaten = false;
        }
      }
      else {
        $RemoteDaten = false;
      }
    }
    if ($InfluxDB_local) {
      $rc = $funktionen->influx_local( $aktuelleDaten );
    }
  }
  else {
    $rc = $funktionen->influx_local( $aktuelleDaten );
  }
  if (is_file( $Pfad."/1.user.config.php" )) {
    // Ausgang Multi-Regler-Version
    $Zeitspanne = (7 - (time( ) - $Start));
    $funktionen->log_schreiben( "Multi-Regler-Ausgang. ".$Zeitspanne, "   ", 2 );
    if ($Zeitspanne > 0) {
      sleep( $Zeitspanne );
    }
    break;
  }
  else {
    $funktionen->log_schreiben( "Schleife: ".($s)." Zeitspanne: ".(floor( (56 - (time( ) - $Start)) / $Wiederholungen )), "   ", 7 );
    sleep( floor( (56 - (time( ) - $Start)) / $Wiederholungen ));
  }
  if ($Wiederholungen <= $i or $i >= 1) {
    //  Die RCT Wechselrichter dürfen nur einmal pro Minute ausgelesen werden!
    $funktionen->log_schreiben( "Schleife ".$i." Ausgang...", "   ", 5 );
    break;
  }
  $i++;
} while (($Start + 54) > time( ));

/*********************************************************************
//  Sollen Nachrichten an einen Messenger gesendet werden?
//  Bei einer Multi-Regler-Version sollte diese Funktion nur bei einem
//  Gerät aktiviert sein.
*********************************************************************/
if (isset($Messenger) and $Messenger == true) {
  $funktionen->log_schreiben( "Nachrichten versenden...", "   ", 8 );
  require ($Pfad."/meldungen_senden.php");
}
$funktionen->log_schreiben( "OK. Datenübertragung erfolgreich.", "   ", 7 );
curl_close( $rCurlHandle );

/*************************/

Ausgang:

/*************************/
error_reporting(E_ALL);
$funktionen->log_schreiben( "-------------   Stop   hm_geraet.php    -------------------------- ", "|--", 6 );
return;
?>
