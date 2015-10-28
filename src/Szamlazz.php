<?php
/**
 * Created by PhpStorm.
 * User: Span
 * Date: 2015.10.27.
 * Time: 11:34
 */

namespace Span\Szamlazz;


class Szamlazz {

    private $data;
    protected $USERNAME = '';
    protected $PASSWORD = '';
    protected $KEYPASSWORD = '';
    private $eszamla;
    private $download;

    public function __construct($data, $download = true, $eszamla = false) {
        $this->data = $data;
        $this->eszamla = $eszamla;
        $this->download = $download;
    }

    public function createInvoice() {
        $szamla = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><xmlszamla xmlns="http://www.szamlazz.hu/xmlszamla" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.szamlazz.hu/xmlszamla xmlszamla.xsd"></xmlszamla>');

        $beallitasok = $szamla->addChild('beallitasok');

        $beallitasok->addChild('felhasznalo', $this->USERNAME);
        $beallitasok->addChild('jelszo', $this->PASSWORD);

        if ($this->eszamla) {
            $beallitasok->addChild('eszamla', 'true');
            $beallitasok->addChild('kulcstartojelszo', $this->KEYPASSWORD);
        }

        if ($this->download) {
            $beallitasok->addChild('szamlaLetoltes', 'true');
        }

        $fejlec = $szamla->addChild('fejlec');
        $fejlec->addChild('keltDatum', date('Y-m-d') );
        $fejlec->addChild('teljesitesDatum', date('Y-m-d', $this->data['teljesitesDatum']));
        $fejlec->addChild('fizetesiHataridoDatum', date('Y-m-d', mktime(0, 0, 0, date("m")  , date("d")+7, date("Y"))));
        $fejlec->addChild('fizmod', $this->data['fizmod']);
        $fejlec->addChild('penznem', 'HUF');
        $fejlec->addChild('szamlaNyelve', 'hu');
        $fejlec->addChild('megjegyzes', '');
        $fejlec->addChild('rendelesSzam', $this->data['rendelesSzam']);
        $fejlec->addChild('elolegszamla', 'false');
        $fejlec->addChild('vegszamla', 'false');

        $elado = $szamla->addChild('elado');
        $elado->addChild('bank', 'OTP Bank');
        $elado->addChild('bankszamlaszam', '11111111-22222222-11111111');
        $elado->addChild('emailReplyto', 'E-mail');
        $elado->addChild('emailTargy', 'E-mail tárgy');
        $elado->addChild('emailSzoveg', 'E-mail szöveg');

        $vendor = $this->data['vendor'];

        $vevo = $szamla->addChild('vevo');
        $vevo->addChild('nev', ($vendor['name'] ? $vendor['name'].' - ' : '').$vendor['first_name'].' '.$vendor['last_name'] );
        $vevo->addChild('irsz', $vendor['zip']);
        $vevo->addChild('telepules', $vendor['city']);
        $vevo->addChild('cim', $vendor['address']);
        $vevo->addChild('email', $vendor['email']);
        $vevo->addChild('sendEmail', true);
        $vevo->addChild('adoszam', $vendor['adoszam']);

        $brutto = $vendor['price'];
        $netto = round($brutto / 1.27);

        $tetelek = $szamla->addChild('tetelek');
        $tetel = $tetelek->addChild('tetel');
        $tetel->addChild('megnevezes', 'Havi előfizetési díj');
        $tetel->addChild('mennyiseg', 1);
        $tetel->addChild('mennyisegiEgyseg', 'db');
        $tetel->addChild('nettoEgysegar', $netto);
        $tetel->addChild('afakulcs', '27');
        $tetel->addChild('nettoErtek', $netto);
        $tetel->addChild('afaErtek', $brutto - $netto);
        $tetel->addChild('bruttoErtek', $brutto);
        $tetel->addChild('megjegyzes', '');

        $xml = $szamla->asXML();

        $date = date('Ym');

        if (!file_exists('/data/szamlazz')) mkdir('/data/szamlazz', 0755, true);
        if (!file_exists('/data/szamlazz/'.$date)) mkdir('/data/szamlazz/'.$date, 0755, true);
        $file = fopen('/data/szamlazz/'.$date.'/'.$vendor['id'].'xml', 'w+');
        fwrite($file, $xml);
        fclose($file);

        return $this->sendXML('/data/szamlazz/'.$date.'/'.$vendor['id'].'xml', $vendor['id'], $date);
    }

    private function sendXML($xmlfile = 'invoice.xml', $vendor_id, $date){

        if (!file_exists('/data/szamlazz/'.$date.'/pdf')) mkdir('/data/szamlazz/'.$date.'/pdf', 0755, true);

        $ch = curl_init("https://www.szamlazz.hu/szamla/");
        $pdf = '/data/szamlazz/'.$date.'/pdf/'.$vendor_id.'.pdf';
        $fp = fopen($pdf, "w");
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array('action-xmlagentxmlfile'=>'@'.$xmlfile));
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        if(mime_content_type($pdf) == 'text/plain'){
            return false;
            /*
            Nem pdf típusú féjl érkezett vissza a válaszban. Ez általában hibát jelez. Az email tartalmazni fogja a szamlazz.hu rendszeréből visszaérkezett hibajelentést.
            */
            //mail("email@example.com", "Hiba a számla készítése során", convertBack("Hiba történt! ORDER ID: ".$orderid."\n".file_get_contents($pdf)));
        }else{
            return true;
            /*
            A számla elkészült! Beállítjuk a rendelés számlázási státuszát 1-re, hogy nehogy mégegyszer kiszámlázásra kerüljön.
            */
            //mysql_query("UPDATE `jos_vm_orders` SET `invoiced` = 1 WHERE order_id = '".$orderid."'");
        }
    }
}