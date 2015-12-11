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
    private $directory;
    private $send_email;

    public function __construct($data, $username, $password, $directory, $send_email = false, $download = true, $eszamla = false) {
        $this->data = $data;
        $this->eszamla = $eszamla;
        $this->download = $download;
        $this->USERNAME = $username;
        $this->PASSWORD = $password;
        $this->directory = $directory;
        $this->send_email = $send_email;
    }

    public function createInvoice() {
        $szamla = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><xmlszamla xmlns="http://www.szamlazz.hu/xmlszamla" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.szamlazz.hu/xmlszamla xmlszamla.xsd"></xmlszamla>');

        $beallitasok = $szamla->addChild('beallitasok');

        $beallitasok->addChild('felhasznalo', $this->USERNAME);
        $beallitasok->addChild('jelszo', $this->PASSWORD);

        if ($this->eszamla) {
            $beallitasok->addChild('eszamla', 'true');
            $beallitasok->addChild('kulcstartojelszo', $this->KEYPASSWORD);
        } else {
            $beallitasok->addChild('eszamla', 'false');
        }

        if ($this->download) {
            $beallitasok->addChild('szamlaLetoltes', 'true');
        }

        $fejlec = $szamla->addChild('fejlec');
        $fejlec->addChild('keltDatum', date('Y-m-d') );
        $fejlec->addChild('teljesitesDatum', $this->data['teljesitesDatum']);
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
        $elado->addChild('emailReplyto', 'info@alompar.hu');
        $elado->addChild('emailTargy', 'E-mail tárgy');
        $elado->addChild('emailSzoveg', 'E-mail szöveg');

        $vendor = $this->data['vendor'];

        $vevo = $szamla->addChild('vevo');
        $vevo->addChild('nev', ($vendor['name'] ? $vendor['name'].' - ' : '').$vendor['first_name'].' '.$vendor['last_name'] );
        $vevo->addChild('irsz', $vendor['zip']);
        $vevo->addChild('telepules', $vendor['city']);
        $vevo->addChild('cim', $vendor['address']);
        $vevo->addChild('email', $vendor['email']);
        if ($this->send_email) {
            $vevo->addChild('sendEmail', 'true');
        } else {
            $vevo->addChild('sendEmail', 'false');
        }
        $vevo->addChild('adoszam', $vendor['adoszam']);

        $brutto = $vendor['price_br'];
        $netto = $vendor['price_net'];

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

        if (!file_exists($this->directory.'/data/szamlazz')) mkdir($this->directory.'/data/szamlazz', 0755, true);
        if (!file_exists($this->directory.'/data/szamlazz/'.$date)) mkdir($this->directory.'/data/szamlazz/'.$date, 0755, true);
        $file = fopen($this->directory.'/data/szamlazz/'.$date.'/'.$this->data['rendelesSzam'].'.xml', 'w+');
        fwrite($file, $xml);
        fclose($file);

        return $this->sendXML($this->directory.'/data/szamlazz/'.$date.'/'.$this->data['rendelesSzam'].'.xml', $this->data['rendelesSzam'], $date);
    }

    private function sendXML($xmlfile = 'invoice.xml', $subscription_id, $date){

        if (!file_exists($this->directory.'/data/szamlazz/'.$date.'/pdf')) mkdir($this->directory.'/data/szamlazz/'.$date.'/pdf', 0755, true);

        $ch = curl_init("https://www.szamlazz.hu/szamla/");
        $pdf = $this->directory.'/data/szamlazz/'.$date.'/pdf/'.$subscription_id.'.pdf';

        $cookie_file = $this->directory.'/data/szamlazz/szamlazz_cookie.txt';
        if (!file_exists($cookie_file)) {
            $cookie = fopen($cookie_file, 'w');
            fwrite($cookie, '');
            fclose($cookie);
        }

        $fp = fopen($pdf, "w");
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array('action-xmlagentxmlfile'=> '@'.$xmlfile));
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
        if (file_exists($cookie_file) && filesize($cookie_file) > 0) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
        }
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        if(mime_content_type($pdf) == 'text/plain'){
            $result = false;
        }else{
            $result = true;
        }

        $response = array(
            'result' => $result,
            'body' => $pdf
        );

        return $response;
    }
}