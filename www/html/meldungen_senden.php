<?php

/******************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2015-2018]   [Ulrich Kunz]
//
//  Dieses Programm ist freie Software. Sie können es unter den Bedingungen
//  der GNU General Public License, wie von der Free Software Foundation
//  veröffentlicht, weitergeben und/oder modifizieren, entweder gemäß
//  Version 3 der Lizenz oder (nach Ihrer Option) jeder späteren Version.
//
//  Die Veröffentlichung dieses Programms erfolgt in der Hoffnung, dass es
//  Ihnen von Nutzen sein wird, aber OHNE IRGENDEINE GARANTIE, sogar ohne
//  die implizite Garantie der MARKTREIFE oder der VERWENDBARKEIT FÜR EINEN
//  BESTIMMTEN ZWECK. Details finden Sie in der GNU General Public License.
//
//  Ein original Exemplar der GNU General Public License finden Sie hier:
//  http://www.gnu.org/licenses/
//
//  Dies ist ein Programmteil des Programms "Solaranzeige"
//
//  Es dient dem Übertragen von Meldungen an den Messenger Pushover, Signal
//  oder WhatsAPP
//  Detail Informationen finden Sie im Dokument "Nachrichten_senden.pdf"
//
//  Welche Meldungen und wann übertragen werden, wird hier festgelegt.
//  Dieses ist nur als Beispiel zu sehen. Da Jeder ganz bestimmte Meldungen
//  übertragen möchte, müssen Sie hier selber die Programmierung
//  übernehmen. Vielleicht hilft auch der Eine oder Andere im Support Forum.
//
//  Diese Funktion ist nur eingeschaltet, wenn in der user.config.php
//  $Meldungen = true  eingetragen ist.
//  Zur Unterscheidung kann die Variable $GeraeteNummer benutzt werden.
//
******************************************************************************/
//  $Tracelevel = 10;  //  1 bis 10  10 = Debug
$Meldungen = array();
//  Ist der Standort in der user.config.php angegeben?
//  Wenn nicht dan Standort Frankfurt nehmen
if (isset($Breitengrad)) {
  $breite = $Breitengrad;
}
else {
  $breite = 50.1143999;
 }
if (isset($Laengengrad)) {
  $laenge = $Laengengrad;
}
else {
  $laenge = 8.6585178;
}

/******************************************************************************
//  Es werden die wichtigsten Daten auf der Influx Datenbank gelesen, die für
//  die Versendung der Messenger Nachrichten benötigt werden.
//
******************************************************************************/
if ($InfluxDB_local === true) {
  //
  //  Wann ist Mitternacht?
  $HeuteMitternacht = strtotime( 'today midnight' );
  $funktionen->log_schreiben( "Mitternacht: ".date( "d.m.Y H:i:s", $HeuteMitternacht )." Timestamp: ".$HeuteMitternacht, "*  ", 9 );
  //
  //  Sonnenaufgang und Sonnenuntergang berechnen (default Standort ist Frankfurt)
  $now = time( );
  $gmt_offset = 1 + date( "I" );
  $zenith = 50 / 60;
  $zenith = $zenith + 90;
  $Sonnenuntergang = date_sunset( $now, SUNFUNCS_RET_TIMESTAMP, $breite, $laenge, $zenith, $gmt_offset );
  $Sonnenaufgang = date_sunrise( $now, SUNFUNCS_RET_TIMESTAMP, $breite, $laenge, $zenith, $gmt_offset );

  /****************************************************************************
  //  NACHRICHTEN BLOCK  START      NACHRICHTEN BLOCK  START      BLOCK  START
  //  NACHRICHTEN BLOCK  START      NACHRICHTEN BLOCK  START      BLOCK  START
  //  NACHRICHTEN BLOCK  START      NACHRICHTEN BLOCK  START      BLOCK  START
  ****************************************************************************/
  //**************************************************************************
  //  SONNENUNTERGANG      SONNENUNTERGANG      SONNENUNTERGANG      SONNEN
  //  Nach Sonnenuntergang wird der Ertrag von Heute gesendet.
  //
  //**************************************************************************
  //  Step 1
  //  Es wird abgefragt, ob die Meldung Heute schon einmal gesendet wurde.
  //  In dem Beispiel wird davon ausgegangen, dass die Meldung nur einmal am
  //  Tage gesendet wird. Ist der 2. Parameter eine 0 dann wird nichts
  //  verändert sondern nur abgefragt. Ist es eine Zahl größer 0 dann wird
  //  Der Zähler gespeichert. Damit kann man die Anzahl der Meldungen
  //  für eine bestimmte Nachricht steuern.
  //  Die Rückgabe ist:
  //  -------------------
  //  $rc[0] = Timestamp an dem die Meldung gesendet wurde.
  //  $rc[1] = Anzahl der gesendeten Meldungen.
  //  $rc[2] = Meldungsname. In diesem Fall "Sonnenuntergang"
  $rc = $funktionen->po_messageControl( "Sonnenuntergang", 0, $GeraeteNummer, $Messengerdienst[1] );
  $funktionen->log_schreiben( "Eintrag: Sonnenuntergang  Datum: ".date( "d.m.Y", $rc[0] )." Anzahl: ".$rc[1], "*  ", 8 );
  if ($rc === false or date( "Ymd", $rc[0] ) <> date( "Ymd" )) {
    //  Entweder es wurde noch nie der Ertrag gesendet oder es wird geprüft
    //  ob Heute schon gesendet wurde. Man könnte hier auch die Anzahl der Nachrichten abfragen,
    //  wenn man mehrmals am Tage diese Meldung schicken möchte.
    //  In diesem Beispiel wird nur eine Nachricht pro Tag versendet.
    //
    //  Step 2
    //  Hier wird die Meldung generiert!
    //  Das kann über die Datenbank geschehen, es können aber auch direkt die Variablen
    //  des Hauptspripts "§aktuelleDaten["..."] benutzt werden.
    //  In diesem Beispiel wird die Datenbank aus der user.config.php [$InfluxDBLokal] benutzt.
    $aktuelleDaten["Query"] = "db=".$InfluxDBLokal."&q=".urlencode( "select last(Wh_Heute) from Summen where time > ".$HeuteMitternacht."000000000  and time <= now() limit 5" );
    if (($Sonnenuntergang + 600) < time( )) {
      //  10 Minuten nach Sonnenuntergang.
      //  Ertrag senden ...
      // Die Influx Datenbank abfragen, ob ein bestimmtes Ereignis passiert ist.
      $rc = $funktionen->po_influxdb_lesen( $aktuelleDaten );
      $funktionen->log_schreiben( var_export( $rc, 1 ), "*  ", 9 );
      $funktionen->log_schreiben( $aktuelleDaten["Query"], "*  ", 9 );
      // Der Wert "Wh_Heute" muss in der Datenbank "solaranzeige" im Measurement "Summen" vorhanden sein!
      $Meldungen["Wh_Heute"] = $rc["results"][0]["series"][0]["values"][0][1];
      $Meldungen["Timestamp"] = $rc["results"][0]["series"][0]["values"][0][0];
      $funktionen->log_schreiben( print_r( $Meldungen, 1 ), "*  ", 9 );
      //  Step 3
      //  Die Nachricht, die gesendet werden soll, wird hier zusammen
      //  gebaut.
      $Nachricht = "Solaranzeige Gerät: ".$GeraeteNummer."  \nSonnenuntergang: Heute am ".date( "d.m.Y H:i", $Sonnenuntergang )." wurden ".$Meldungen["Wh_Heute"]." Wh erzeugt. ";
      //
      //  Step 4
      //  Liefert die Datenbank die nötigen Daten?
      if (isset($rc["results"][0]["series"][0])) {
        //  Die Query liefert ein Ergebnis, das wird an dieser JSON Variable erkannt.
        $funktionen->log_schreiben( strip_tags( $Nachricht ), "*  ", 6 );
        //  Step 5
        //  Soll die Nachricht an mehrere Empfänger gesendet werden?
        //  Das kann auch an Pushover, Signal und WhatsApp gemischt gesendet werden.
        for ($Ui = 1; $Ui <= count( $User_Key ); $Ui++) {
          //  Die Nachricht wird an alle Empfänger gesendet, die in der
          //  user.config.php stehen.
          $funktionen->log_schreiben( "Nachricht wird bald versendet an User_Key[".$Ui."] ".$User_Key[$Ui], "*  ", 9 );
          $rc = $funktionen->po_send_message( $API_Token[$Ui], $User_Key[$Ui], $Nachricht, 0, "", $Messengerdienst[$Ui] );
          if ($rc) {
            $funktionen->log_schreiben( "Nachricht wurde versendet an ".$Messengerdienst[$Ui]." mit Rufnummer: ".$User_Key[$Ui]." und Key: ".$API_Token[$Ui], "   ", 6 );
          }
        }
        //  Step 6
        //  Es wird festgehalten, wann die Nachricht gesendet wurde und eventuell
        //  das wievielte mal. (2. Parameter) In dem Beispiel gibt es nur eine
        //  Meldung pro Tag.
        $rc = $funktionen->po_messageControl( "Sonnenuntergang", 1, $GeraeteNummer, $Messengerdienst[1] );
      }
    }
  }

  /****************************************************************************
  //  NACHRICHTEN BLOCK  STOP      NACHRICHTEN BLOCK  STOP      BLOCK  STOP
  //  NACHRICHTEN BLOCK  STOP      NACHRICHTEN BLOCK  STOP      BLOCK  STOP
  //  NACHRICHTEN BLOCK  STOP      NACHRICHTEN BLOCK  STOP      BLOCK  STOP
  ****************************************************************************/


  /****************************************************************************
  //  NACHRICHTEN BLOCK  START      NACHRICHTEN BLOCK  START      BLOCK  START
  //  NACHRICHTEN BLOCK  START      NACHRICHTEN BLOCK  START      BLOCK  START
  //  NACHRICHTEN BLOCK  START      NACHRICHTEN BLOCK  START      BLOCK  START
  //  Hier kann Ihre Abfrage stehen. Diese Datei wird bei einem
  //  Update nicht ueberschrieben.
  ****************************************************************************/





  /****************************************************************************
  //  NACHRICHTEN BLOCK  STOP      NACHRICHTEN BLOCK  STOP      BLOCK  STOP
  //  NACHRICHTEN BLOCK  STOP      NACHRICHTEN BLOCK  STOP      BLOCK  STOP
  //  NACHRICHTEN BLOCK  STOP      NACHRICHTEN BLOCK  STOP      BLOCK  STOP
  ****************************************************************************/
}
else {
  $funktionen->log_schreiben( "Die lokale Datenbank ist ausgeschaltet. Die Messengerdienste stehen dadurch nicht zur Verfügung.", "*  ", 3 );
}
return;
?>