<?php
/*
 * PHP SOAP - How to create a SOAP Server and a SOAP Client
 */

ini_set( "soap.wsdl_cache_enabled", 0 );
ini_set( 'soap.wsdl_cache_ttl', 0 );

//a basic API class
class MyAPI {


    ///////////////////////////////////////////////////////////////
    // P U B L I C   S O A P   F U N C T I O N S                 //
    ///////////////////////////////////////////////////////////////

    public function getCMTSParams($cmMAC,$cmCommunity) {
        $CmCMTS = array(
                'CMTS' => '',
                'CMIP' => '',
                'PTR' => 0,
                'decMAC' => '',
                'CMStatus' => 0
                );

	$CMTS = $this->getCMTS();

        $decMAC = $this->getMacDecimal($cmMAC);
        $CmCMTS['decMAC'] = $decMAC;
        // We walk through the CMTSes to find on which one the CM is connected
        foreach ($CMTS as $key => $value) {
          $CMTSVAL = $this->getCMCMTSStatus($CMTS[$key]['IP'],$CMTS[$key]['snmpCommunity'],$decMAC);
          if ($CMTSVAL['PTR'] > 0) {
            $CmCMTS['CMTS'] = $key;
            $CmCMTS['CMTSName'] = $CMTS[$key]['name'];
            $CmCMTS['CMIP'] = $CMTSVAL['CMIP'];
            $CmCMTS['PTR'] = $CMTSVAL['PTR'];
            $CmCMTS['CMStatus'] = $CMTSVAL['CMStatus'];
            return array($CmCMTS['CMTS'],$CmCMTS['CMIP'],$CmCMTS['decMAC'],$CmCMTS['PTR'],$CmCMTS['CMStatus'],$CMTSVAL['UsVals'],$CMTSVAL['primDsVals']);
          }
          #return array($CMTS[$key]['snmpCommunity'],$CmCMTS['CMIP'],$CmCMTS['decMAC'],$CmCMTS['PTR'],$CmCMTS['CMStatus'],$CMTSVAL['UsVals'],$CMTSVAL['primDsVals']);
 	
        }
        return array($CmCMTS['CMTS'],$CmCMTS['CMIP'],$CmCMTS['decMAC'],$CmCMTS['PTR'],$CmCMTS['CMStatus'],$CMTSVAL['UsVals'],$CMTSVAL['primDsVals']);

    }

    public function getDeviceIP($deviceMAC) {

	$CMTS = $this->getCMTS();
        $decMAC = $this->getMacDecimal($deviceMAC);
        foreach ($CMTS as $key => $value) {
          $IPMAC = $this->getIP($CMTS[$key]['IP'],$CMTS[$key]['snmpCommunity'],$decMAC,$CMTS[$key]['vendor']);
          if ($IPMAC['IP'] != '0.0.0.0') {
            return array($IPMAC['IP'],$IPMAC['PMAC']);
          }
        }
        return array('0.0.0.0','00:00:00:00:00:00');

    }

    public function getCMProps($CMIP,$cmCommunity) {
      $CmSysDescr = $this->getCMSysDescr($CMIP,$cmCommunity);
      return array($CmSysDescr['Model'],$CmSysDescr['Descr'],$CmSysDescr['Vendor'],$CmSysDescr['FW'],$CmSysDescr['Uptime']);
    }


    public function getCMDsVals($CMIP,$cmCommunity) {
      $CmDSVals = $this->getCMDsSNMP($CMIP,$cmCommunity);
      return $CmDSVals;
    }

    public function getUSDetail($CMTSId,$PTR) {
      $CMTS = $this->getCMTS();
      $CMTSCOMMUNITY = $CMTS[$CMTSId]['snmpCommunity'];
      $CMTSIP = $CMTS[$CMTSId]['IP'];
      $Vendor = $CMTS[$CMTSId]['vendor'];
      $CmDSVals = $this->getUSDetailSNMP($CMTSIP,$CMTSCOMMUNITY,$PTR);
      return $CmDSVals;
    }

    public function getCMUsVals($CMIP,$cmCommunity) {
      $CmUSVals = $this->getCMUsSNMP($CMIP,$cmCommunity);
      return $CmUSVals;
    }


    public function getCMFDBRecords($CMIP,$CMTSId,$cmCommunity) {
      $CMTS = $this->getCMTS();
      $CMTSCOMMUNITY = $CMTS[$CMTSId]['snmpCommunity'];
      $CMTSIP = $CMTS[$CMTSId]['IP'];
      $Vendor = $CMTS[$CMTSId]['vendor'];
      $CmFDBRecords = $this->getCMFDBSNMP($CMIP,$cmCommunity,$CMTSIP,$CMTSCOMMUNITY,$Vendor);
      return $CmFDBRecords;
    }

    public function getWAN($MAC,$CMTSId,$cmCommunity,$CMIP) {
      $CMTS = $this->getCMTS();
      $CMTSCOMMUNITY = $CMTS[$CMTSId]['snmpCommunity'];
      $CMTSIP = $CMTS[$CMTSId]['IP'];
      $Vendor = $CMTS[$CMTSId]['vendor'];
      $WANRecords = $this->getWANRecords($CMIP,$cmCommunity,$MAC,$CMTSIP,$CMTSCOMMUNITY,$Vendor);
      return $WANRecords;
    }



    ///////////////////////////////////////
    // Local SNMP Functions              //
    ///////////////////////////////////////


    private function getCMCMTSStatus($IP,$COMMUNITY,$decMAC) {
        snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
        $RTN = array( // array for returning the values of CM on particular CMTS
                'PTR' => 0,
                'CMIP' => '',
                'CMStatus' => 0);
        $ptrOid = '.1.3.6.1.2.1.10.127.1.3.7.1.2.' . $decMAC; // OID to get the PTR based on CM MAC in decimal value
        $ptr = snmp2_get($IP,$COMMUNITY,$ptrOid);
        $ptr = eregi_replace("INTEGER: ","",$ptr); // The result is integer value or NULL

        if ($ptr > 0) { // from now on basicly all the values will be gathered via PTR
          $RTN['PTR'] = $ptr;
          $CMIPOid = '.1.3.6.1.2.1.10.127.1.3.3.1.3.' . $ptr;
          $CMIP = snmp2_get($IP,$COMMUNITY,$CMIPOid);
          $RTN['CMIP'] = eregi_replace("IpAddress: ","",$CMIP); // get the CM IP address from the CMTS
          $CMStatusOid = '.1.3.6.1.2.1.10.127.1.3.3.1.9.' . $ptr;
          $CMStatus = snmp2_get($IP,$COMMUNITY,$CMStatusOid);
          $RTN['CMStatus'] = eregi_replace("INTEGER: ","",$CMStatus); // get the CM Status from the CMTS
          // Get primary DS index
          $PDSIOid = '.1.3.6.1.2.1.10.127.1.3.3.1.4.' . $ptr;
          $PDSIndex = snmp2_get($IP,$COMMUNITY,$PDSIOid);
          $PDSIndex = eregi_replace("INTEGER: ","",$PDSIndex);
          // if the CM is ranging, not online or registered as Docsis 1.x/2.0 this value will be returned
          $UsSNROid = '.1.3.6.1.4.1.4491.2.1.20.1.4.1.4.' . $ptr;
          $UsVals = snmp2_real_walk($IP,$COMMUNITY,$UsSNROid);

          if ($UsVals != "No Such Instance currently exists at this OID") {
             $US = array();
             $i = 0;
             // This is a Docsis 3.0 modem


             foreach ($UsVals as $key => $value) { //
                $value = eregi_replace("INTEGER: ","",$value);
                $ifIndex = eregi_replace(".1.3.6.1.4.1.4491.2.1.20.1.4.1.4." . $ptr . ".","",$key);
               
                $rangingStatus = snmp2_get($IP,$COMMUNITY,'.1.3.6.1.4.1.4491.2.1.20.1.4.1.12.' . $ptr . '.' . $ifIndex);
                $rangingStatus = eregi_replace("INTEGER: ","",$rangingStatus);
                $modulationType = snmp2_get($IP,$COMMUNITY,'.1.3.6.1.4.1.4491.2.1.20.1.4.1.2.' . $ptr . '.' . $ifIndex);
                $modulationType = eregi_replace("INTEGER: ","",$modulationType);
                $USifIndexParams = $this->snmpUSDetailIfIndexVals($IP,$COMMUNITY,$ifIndex,$ptr);


                $US['us'][$i++] = array('ifIndex:' => $ifIndex, 'snr:' => $value, 'rangingStatus:' => $this->docsIf3CmtsCmUsStatusRangingStatus($rangingStatus), 'modulationType:' => $this->docsIf3CmtsCmUsStatusModulationType($modulationType),
                                        'freq:' => $USifIndexParams['ifFreq'], 'descr:' => $USifIndexParams['ifDescr'], 'util:' => $USifIndexParams['Util']);
             }
             $RTN['UsVals'] = json_encode($US);
          } else {
            $PUSIOid = '.1.3.6.1.2.1.10.127.1.3.3.1.5.' . $ptr;
            $PUSIndex = snmp2_get($IP,$COMMUNITY,$PUSIOid);
            $PUSIndex = eregi_replace("INTEGER: ","",$PUSIndex);

            $USifIndexParams = $this->snmpUSIfIndexVals($IP,$COMMUNITY,$PUSIndex);
            $US['us'][0] = array('ifIndex:' => $PUSIndex, 'snr:' => $USifIndexParams['usSnr'], 'rangingStatus:' => 'na', 'modulationType:' => 'na', 'freq:' => $USifIndexParams['ifFreq'], 'descr:' => $USifIndexParams['ifDescr']);
            $RTN['UsVals'] = json_encode($US);
          }
          $DSifIndexParams = $this->snmpDSIfIndexVals($IP,$COMMUNITY,$PDSIndex);
          $DS['primDs'] = array('ifIndex:' => $PDSIndex, 'moulation:' => $DSifIndexParams['ifModulation'], 'freq:' => $DSifIndexParams['ifFreq'], 'annex:' => $DSifIndexParams['ifAnnex'], 'ifDescr:' => $DSifIndexParams['ifDescr']);
          $RTN['primDsVals'] = json_encode($DS);
          $RTN['CMStatus'] = $this->docsIfCmtsCmStatusValue($RTN['CMStatus']);
          return $RTN;
        }
        return $RTN;
    }


   private function getUSDetailSNMP($IP,$COMMUNITY,$ptr) {
        snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
        $RTN = array( // array for returning the values of CM on particular CMTS
                'USVals' => '');

          $UsSNROid = '.1.3.6.1.4.1.4491.2.1.20.1.4.1.4.' . $ptr;
          $UsVals = snmp2_real_walk($IP,$COMMUNITY,$UsSNROid);

          if ($UsVals != "No Such Instance currently exists at this OID") {
             $US = array();
             $i = 0;
             // This is a Docsis 3.0 modem
             $UsSNROid = '.1.3.6.1.4.1.4491.2.1.20.1.4.1.4.' . $ptr;
             $UsVals = snmp2_real_walk($IP,$COMMUNITY,$UsSNROid);
             $UsUnerrOid = '.1.3.6.1.4.1.4491.2.1.20.1.4.1.7.' . $ptr;
             $UsUnerrVals = snmp2_real_walk($IP,$COMMUNITY,$UsUnerrOid);
             $UsCorrOid = '.1.3.6.1.4.1.4491.2.1.20.1.4.1.8.' . $ptr;
             $UsCorrVals = snmp2_real_walk($IP,$COMMUNITY,$UsCorrOid);
             $UsUncorrOid = '.1.3.6.1.4.1.4491.2.1.20.1.4.1.9.' . $ptr;
             $UsUnCorrVals = snmp2_real_walk($IP,$COMMUNITY,$UsUncorrOid);
             foreach ($UsVals as $key => $value) { //
                $value = eregi_replace("INTEGER: ","",$value);
                $ifIndex = eregi_replace(".1.3.6.1.4.1.4491.2.1.20.1.4.1.4." . $ptr . ".","",$key);

                $usUnerrOidt = $UsUnerrOid . '.' . $ifIndex;
                $usUnerr = $UsUnerrVals[$usUnerrOidt];
                $usUnerr = eregi_replace("Counter32: ","",$usUnerr);

                $UsCorrOidt = $UsCorrOid . '.' . $ifIndex;
                $usCorr = $UsCorrVals[$UsCorrOidt];
                $usCorr = eregi_replace("Counter32: ","",$usCorr);

                $UsUncorrOidt = $UsUncorrOid . '.' . $ifIndex;
                $usUnCorr = $UsUnCorrVals[$UsUncorrOidt];
                $usUnCorr = eregi_replace("Counter32: ","",$usUnCorr);

                $rangingStatus = snmp2_get($IP,$COMMUNITY,'.1.3.6.1.4.1.4491.2.1.20.1.4.1.12.' . $ptr . '.' . $ifIndex);
                $rangingStatus = eregi_replace("INTEGER: ","",$rangingStatus);
                $modulationType = snmp2_get($IP,$COMMUNITY,'.1.3.6.1.4.1.4491.2.1.20.1.4.1.2.' . $ptr . '.' . $ifIndex);
                $modulationType = eregi_replace("INTEGER: ","",$modulationType);
                $USifIndexParams = $this->snmpUSDetailIfIndexVals($IP,$COMMUNITY,$ifIndex,$ptr);

                $US['us'][$i++] = array('ifIndex:' => $ifIndex, 'snr:' => $value, 'rangingStatus:' => $this->docsIf3CmtsCmUsStatusRangingStatus($rangingStatus), 'modulationType:' => $this->docsIf3CmtsCmUsStatusModulationType($modulationType),
                                        'freq:' => $USifIndexParams['ifFreq'], 'descr:' => $USifIndexParams['ifDescr'], 'NoError:' => $usUnerr, 'Corrected:' => $usCorr, 'UnCorrected:' => $usUnCorr, 'Util' => $USifIndexParams['Util']);

             }
             return json_encode($US);
          } else {
             $PUSIOid = '.1.3.6.1.2.1.10.127.1.3.3.1.5.' . $ptr;
             $PUSIndex = snmp2_get($IP,$COMMUNITY,$PUSIOid);
             $PUSIndex = eregi_replace("INTEGER: ","",$PUSIndex);


             $USifIndexParams = $this->snmpUSDetailIfIndexVals($IP,$COMMUNITY,$PUSIndex,$ptr);
             $US['us'][0] = array('ifIndex:' => $PUSIndex, 'snr:' => $USifIndexParams['usSnr'], 'rangingStatus:' => 'na', 'modulationType:' => 'na', 'freq:' => $USifIndexParams['ifFreq'], 'descr:' => $USifIndexParams['ifDescr'], 'NoError:' => $USifIndexParams['NoErr'] , 'Corrected:' => $USifIndexParams['Corrected'], 'UnCorrected: ' => $USifIndexParams['UnCorrected'], 'Util' => $USifIndexParams['Util']);


             $RTN['UsVals'] = json_encode($US);
             return json_encode($US);
          }
    }


    private function getCMSysDescr($IP,$COMMUNITY) {
        $RTN = array(
                'Model' => '',
                'Descr' => '',
                'FW'    => '',
                'Uptime'=> '',
                'Vendor'=> ''
                );

        $sysDescr = snmp2_get($IP,$COMMUNITY,'.1.3.6.1.2.1.1.1.0');
        $sysDescr = eregi_replace("STRING: ","",$sysDescr);
	$RTN['Model'] = $sysDescr;
        $arrSysDescr = explode("<<", $sysDescr);
        $sysDescr = eregi_replace(">>","",$arrSysDescr[1]);
        $arrTmp = explode(";",$sysDescr);
        foreach ($arrTmp as $val) {
          if (strpos($val,'MODEL') !== false) {
            $model = explode(":",$val);
            $RTN['Model'] = $model[1];
          }
          if (strpos($val,'VENDOR') !== false) {
            $model = explode(":",$val);
            $RTN['Vendor'] = $model[1];
          }
          if (strpos($val,'SW_REV') !== false) {
            $model = explode(":",$val);
            $RTN['FW'] = $model[1];
          }
        }
        $RTN['Descr'] = $arrSysDescr[0];
        $RTN['Uptime'] = snmp2_get($IP,$COMMUNITY,'.1.3.6.1.2.1.1.3.0');
        $RTN['Uptime'] = eregi_replace("Timeticks: ","",$RTN['Uptime']);
        return $RTN;
    }




    private function getIP($CMTSIP,$CMTSCOMMUNITY,$decMAC,$CMTSVendor) {
        $RTN = array( 'IP' => '', 'PMAC' => '');

        if ($CMTSVendor == "Cisco") {
          $IPOid = '.1.3.6.1.4.1.9.9.116.1.3.1.1.3.';
        } elseif ($CMTSVendor == "Casa") {
          $IPOid = '.1.3.6.1.4.1.20858.10.12.1.3.1.3.';
        }

        $IP = snmp2_get($CMTSIP,$CMTSCOMMUNITY,$IPOid . $decMAC);
        $IP = eregi_replace("IpAddress: ","",$IP);
        if (filter_var($IP, FILTER_VALIDATE_IP) === false) {
          $IP = '0.0.0.0';
        }
        $TMP = explode(".", $IP);
        if ($TMP[0] == 0) {
	  $IP = '0.0.0.0';
        } else {
          $TMPIP = explode(".",$IP);
          if ($TMPIP[0] == 0) {
            $IP = '0.0.0.0';
          }
        }


        if ($IP != '0.0.0.0') {
            if ($CMTSVendor == "Cisco") {
                $MACOid = '.1.3.6.1.4.1.9.9.116.1.3.9.1.2.';
                $PMAC = snmp2_get($CMTSIP,$CMTSCOMMUNITY,$MACOid . $decMAC);
                $PMAC = eregi_replace("Hex-STRING: ","",$PMAC);
                $PMAC = str_replace(' ', ':', $PMAC);

            } elseif ($CMTSVendor == "Casa") {
                $MACOid = '.1.3.6.1.4.1.20858.10.12.1.6.1.5.';
                $PMAC = snmp2_get($CMTSIP,$CMTSCOMMUNITY,$MACOid . $decMAC);
                $PMAC = eregi_replace("Hex-STRING: ","",$PMAC);
		$PMAC = str_replace(' ', ':', $PMAC);
            }

            $RTN['IP'] = $IP;
            $PMAC = rtrim($PMAC,":");
            $RTN['PMAC'] = $PMAC;
            return $RTN;
	}
	$RTN['IP'] = '0.0.0.0';
	$RTN['PMAC'] = '00:00:00:00:00:00';
        return $RTN;

    } 

    private function snmpUSIfIndexVals($IP,$COMMUNITY,$ifIndex,$ptr) {
        $RTN = array(
                'ifDescr' => '',
                'ifFreq' => 0,
                'ifSpeed' => 0,
                'usSnr' => 0
                );

        $ifDescr = snmp2_get($IP,$COMMUNITY,'.1.3.6.1.2.1.2.2.1.2.' . $ifIndex);
        $RTN['ifDescr'] = eregi_replace("STRING: ","",$ifDescr);

        $snr = snmp2_get($IP,$COMMUNITY,'.1.3.6.1.2.1.10.127.1.1.4.1.5.' . $ifIndex);
        $RTN['usSnr'] = eregi_replace("INTEGER: ","",$snr);


        $freq = snmp2_get($IP,$COMMUNITY,'.1.3.6.1.2.1.10.127.1.1.2.1.2.' . $ifIndex);
        $RTN['ifFreq'] = eregi_replace("INTEGER: ","",$freq);

        $speed = snmp2_get($IP,$COMMUNITY,'.1.3.6.1.2.1.2.2.1.5.' . $ifIndex);
        $RTN['ifSpeed'] = eregi_replace("Gauge32: ","",$speed);

        return $RTN;
    }

    private function snmpUSDetailIfIndexVals($IP,$COMMUNITY,$ifIndex,$ptr) {
        $RTN = array(
                'ifDescr' => '',
                'ifFreq' => 0,
                'ifSpeed' => 0,
                'usSnr' => 0,
                'NoErr' => 0,
                'Corrected' => 0,
                'Util' => '0',
                'UnCorrected' => 0 // to use in non Docsis 3.0
                );

        $ifDescr = snmp2_get($IP,$COMMUNITY,'.1.3.6.1.2.1.2.2.1.2.' . $ifIndex);
        $RTN['ifDescr'] = eregi_replace("STRING: ","",$ifDescr);

        $snr = snmp2_get($IP,$COMMUNITY,'.1.3.6.1.2.1.10.127.1.1.4.1.5.' . $ifIndex);
        $RTN['usSnr'] = eregi_replace("INTEGER: ","",$snr);

        $freq = snmp2_get($IP,$COMMUNITY,'.1.3.6.1.2.1.10.127.1.1.2.1.2.' . $ifIndex);
        $RTN['ifFreq'] = eregi_replace("INTEGER: ","",$freq);

        $speed = snmp2_get($IP,$COMMUNITY,'.1.3.6.1.2.1.2.2.1.5.' . $ifIndex);
        $RTN['ifSpeed'] = eregi_replace("Gauge32: ","",$speed);

        $noErr = snmp2_get($IP,$COMMUNITY,'.1.3.6.1.2.1.10.127.1.3.3.1.10.' . $ptr);
        $RTN['NoErr'] = eregi_replace("Counter32: ","",$noErr);

        $Corr = snmp2_get($IP,$COMMUNITY,'.1.3.6.1.2.1.10.127.1.3.3.1.11.' . $ptr);
        $RTN['Corrected'] = eregi_replace("Counter32: ","",$Corr);

        $UnCorr = snmp2_get($IP,$COMMUNITY,'.1.3.6.1.2.1.10.127.1.3.3.1.12.' . $ptr);
        $RTN['UnCorrected'] = eregi_replace("Counter32: ","",$UnCorr);

        $util = snmp2_real_walk($IP,$COMMUNITY,'.1.3.6.1.2.1.10.127.1.3.9.1.3.' . $ifIndex);
        foreach ($util as $uval) {
          $uval = eregi_replace("INTEGER: ","",$uval);
          $RTN['Util'] = $uval;
        }

        return $RTN;
    }

    private function snmpDSIfIndexVals($IP,$COMMUNITY,$ifIndex) {
        $RTN = array(
                'ifDescr' => '',
                'ifFreq' => 0,
                'ifSpeed' => 0,
                'ifModulation' => 0, // to use in non Docsis 3.0
                'ifAnnex' => 0 // to use in non Docsis 3.0
                );

        $ifDescr = snmp2_get($IP,$COMMUNITY,'.1.3.6.1.2.1.2.2.1.2.' . $ifIndex);
        $RTN['ifDescr'] = eregi_replace("STRING: ","",$ifDescr);

        $mod = snmp2_get($IP,$COMMUNITY,'.1.3.6.1.2.1.10.127.1.1.1.1.4.' . $ifIndex);
        $RTN['ifModulation'] = $this->docsIfDownChannelModulation(eregi_replace("INTEGER: ","",$mod));

        $freq = snmp2_get($IP,$COMMUNITY,'.1.3.6.1.2.1.10.127.1.1.1.1.2.' . $ifIndex);
        $RTN['ifFreq'] = eregi_replace("INTEGER: ","",$freq);

        $annex = snmp2_get($IP,$COMMUNITY,'.1.3.6.1.2.1.10.127.1.1.1.1.7.' . $ifIndex);
        $RTN['ifAnnex'] = $this->docsIfDownChannelAnnex(eregi_replace("INTEGER: ","",$annex));

        $speed = snmp2_get($IP,$COMMUNITY,'.1.3.6.1.2.1.2.2.1.5.' . $ifIndex);
        $RTN['ifSpeed'] = eregi_replace("Gauge32: ","",$speed);

        return $RTN;
    }



    private function getCMDsSNMP($CMIP,$COMMUNITY)  {
        $DS = array();
        snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
        $DSFreqListOid = '.1.3.6.1.2.1.10.127.1.1.1.1.2';
        $DSFreqList = snmp2_real_walk($CMIP,$COMMUNITY,$DSFreqListOid);
        $i = 0;
        foreach ($DSFreqList as $key => $value) {

                $value = eregi_replace("INTEGER: ","",$value)/1000000;
                $ifIndex = eregi_replace(".1.3.6.1.2.1.10.127.1.1.1.1.2.","",$key);

                $power = snmp2_get($CMIP,$COMMUNITY,'.1.3.6.1.2.1.10.127.1.1.1.1.6.' . $ifIndex);
                $power = eregi_replace("INTEGER: ","",$power)/10;

                $mod = snmp2_get($CMIP,$COMMUNITY,'.1.3.6.1.2.1.10.127.1.1.1.1.4.' . $ifIndex);
                $mod = $this->docsIfDownChannelModulation(eregi_replace("INTEGER: ","",$mod));

                $annex = snmp2_get($CMIP,$COMMUNITY,'.1.3.6.1.2.1.10.127.1.1.1.1.7.' . $ifIndex);
                $annex = $this->docsIfDownChannelAnnex(eregi_replace("INTEGER: ","",$annex));

                $snr = snmp2_get($CMIP,$COMMUNITY,'.1.3.6.1.2.1.10.127.1.1.4.1.5.' . $ifIndex);
                $snr = eregi_replace("INTEGER: ","",$snr)/10;

                $mr = snmp2_get($CMIP,$COMMUNITY,'.1.3.6.1.2.1.10.127.1.1.4.1.6.' . $ifIndex);
                $mr = eregi_replace("INTEGER: ","",$mr);


                $DS['ds'][$i++] = array('freq:' => $value,'power:' => $power,'modulation:' => $mod,'annex:' => $annex,'snr:' => $snr, 'mcrRefl:' => $mr);
        }

        return json_encode($DS);

    }


    private function getCMUsSNMP($CMIP,$COMMUNITY)  {
        snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
        // Check if it is a Docsis 3.0 modem
        $ifDocsis3 = snmp2_get($CMIP,$COMMUNITY,'.1.3.6.1.4.1.4491.2.1.20.1.1.1.1.2');
        $ifDocsis3 = eregi_replace("INTEGER: ","",$ifDocsis3);

        $US = array();
        $USFreqListOid = '.1.3.6.1.2.1.10.127.1.1.2.1.2';
        $USFreqList = snmp2_real_walk($CMIP,$COMMUNITY,$USFreqListOid);
        $i = 0;
        foreach ($USFreqList as $key => $value) {
                $value = eregi_replace("INTEGER: ","",$value);
                $ifIndex = eregi_replace(".1.3.6.1.2.1.10.127.1.1.2.1.2.","",$key);
                if ($ifDocsis3 > 1) {
                        $power = snmp2_get($CMIP,$COMMUNITY,'.1.3.6.1.4.1.4491.2.1.20.1.2.1.1.' . $ifIndex);
                        $power = eregi_replace("INTEGER: ","",$power)/10;
                        $US['us'][$i++] = array('freq:' => $value,'power:' => $power);
                } else {
                        $power = snmp2_get($CMIP,$COMMUNITY,'.1.3.6.1.2.1.10.127.1.2.2.1.3.2');
                        $power = eregi_replace("INTEGER: ","",$power)/10;
                        $US['us'][$i++] = array('freq:' => $value,'power:' => $power);
                }
        }
        return json_encode($US);
    }


    private function getCMFDBSNMP($CMIP,$CMCOMMUNITY,$CMTSIP,$CMTSCOMMUNITY,$CMTSVendor)  {
        // Check if it is a Docsis 3.0 modem
        snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
        $macArr = array();

        $FDB = array();
        $IPTOMTableOid = '.1.3.6.1.2.1.4.22.1.2';

        $IPTOMTable = snmp2_real_walk($CMIP,$CMCOMMUNITY,$IPTOMTableOid);
        $i = 0;
        foreach ($IPTOMTable as $key => $value) {
                $hexMAC = eregi_replace("STRING: ","",$value);
                $hexMAC = $this->niceMacHex($hexMAC);
                $macArr[$hexMAC] = 1;
                $IP = eregi_replace("$IPTOMTableOid" . ".","",$key);
                $TMP = explode(".", $IP);
                $ifIndex = $TMP[0];
                $IP = $TMP[1] . "." . $TMP[2] . "." . $TMP[3] . "." . $TMP[4];
                $ifDescr = snmp2_get($CMIP,$CMCOMMUNITY,'.1.3.6.1.2.1.2.2.1.2.' . $ifIndex);
                $ifDescr = eregi_replace("STRING: ","",$ifDescr);
                if ($TMP[0] == 17 and $TMP[1] == 192 and $TMP[2] == 168 and $TMP[3] == 100) {
                  $macArr[$hexMAC] = 0;
                } else {
                  $FDB['fdb'][$i++] = array('MAC:' => $hexMAC,'ifIndex:' => $ifIndex, 'ifDescr:' => $ifDescr, 'IP:' => $IP);
		}
        }

        $CMFDBTableOid = '.1.3.6.1.2.1.17.4.3.1.2';

        $CMFDBTable = snmp2_real_walk($CMIP,$CMCOMMUNITY,$CMFDBTableOid);
        foreach ($CMFDBTable as $key => $value) {
                $ifIndex = eregi_replace("INTEGER: ","",$value);
                $decMAC = eregi_replace("$CMFDBTableOid" . ".","",$key);
                $hexMAC = $this->getMacHex($decMAC);
                if ($macArr[$hexMAC] == 1) { continue; }
                $ifDescr = snmp2_get($CMIP,$CMCOMMUNITY,'.1.3.6.1.2.1.2.2.1.2.' . $ifIndex);
                $ifDescr = eregi_replace("STRING: ","",$ifDescr);

                if ($CMTSVendor == "Cisco") {
                        $IPOid = '.1.3.6.1.4.1.9.9.116.1.3.1.1.3.';
                } elseif ($CMTSVendor == "Casa") {
                        $IPOid = '.1.3.6.1.4.1.20858.10.12.1.3.1.3.';
                }

                $IP = snmp2_get($CMTSIP,$CMTSCOMMUNITY,$IPOid . $decMAC);
                $IP = eregi_replace("IpAddress: ","",$IP);
                if (!filter_var($IP, FILTER_VALIDATE_IP)) {
                  $IP = '0.0.0.0';
                }
                $FDB['fdb'][$i++] = array('MAC:' => $hexMAC,'ifIndex:' => $ifIndex, 'ifDescr:' => $ifDescr, 'IP:' => $IP);

        }


        return json_encode($FDB);
    }



    private function getWANRecords($CMIP,$CMCOMMUNITY,$MAC,$CMTSIP,$CMTSCOMMUNITY,$CMTSVendor)  {

        snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);

        $decMac = $this->getMacDecimal($MAC);
        if ($CMTSVendor == "Cisco") {
          $CMTOCPEOID = ".1.3.6.1.4.1.9.9.116.1.3.8.1.3." . $decMac;
        } else {
          $CMTOCPEOID = ".1.3.6.1.4.1.20858.10.12.1.8.1.3." . $decMac;
        }

        $WAN = array();


        $CMTOCPETable = snmp2_real_walk($CMTSIP,$CMTSCOMMUNITY,$CMTOCPEOID);
        #$i = 0;
        foreach ($CMTOCPETable as $key => $value) {
                $hexIP = eregi_replace("Hex-STRING: ","",$value);
                $IP = $this->hexIPtoDecIP($hexIP);
                $ifIndexOid = ".1.3.6.1.2.1.4.20.1.2." . $IP;

                $ifIndex = snmp2_get($CMIP,$CMCOMMUNITY,$ifIndexOid);
                $ifIndex = eregi_replace("INTEGER: ","",$ifIndex);
                if ($ifIndex > 8) { continue; }
		$uptime = 0;
		$uptime = snmp2_get($CMIP,$CMCOMMUNITY,'.1.3.6.1.2.1.1.3.0');
		$uptime = eregi_replace("Timeticks: ","",$uptime);
                $netMask = snmp2_get($CMIP,$CMCOMMUNITY,'.1.3.6.1.2.1.4.20.1.3.' . $IP);
                $netMask = eregi_replace("IpAddress: ","",$netMask);
                echo $ifIndex . " " . $IP . " " . $netMask .  "\n";
                // Get GW from CMTS !!!
                $tmpGW = explode(".",$IP);
                unset($tmpGW[3]);
                $GW = implode('.',$tmpGW);
                $GWArray = snmp2_real_walk($CMTSIP,$CMTSCOMMUNITY,'.1.3.6.1.2.1.4.20.1.1');
                $SUBNET = $IP . "/" . $this->mask2cidr($netMask);
                foreach ($GWArray as $kGW => $vGW) {
	                $GW = eregi_replace("IpAddress: ","",$vGW);
                        if ($this->cidr_match($GW,$SUBNET) == 1) {
		                $WAN = array($IP, $netMask, $GW,$uptime);
                                return $WAN;
                        }
                }
        }

    }




    ///////////////////////////////////////////////
    // Private operations/conversions functions  //
    ///////////////////////////////////////////////

    private function getMacDecimal($mac) {
       $clear_mac = preg_replace('/[^0-9A-F]/i','',$mac);
       $mac_decimal = array();
       for ($i = 0; $i < strlen($clear_mac); $i += 2 ):
          $mac_decimal[] = hexdec(substr($clear_mac, $i, 2));
          endfor;
       return implode('.',$mac_decimal);
    }

    private function getMacHex($mac) {
        $arrMAC = explode(".",$mac);
        $hexMAC = array();
        $h = 0;
        foreach ($arrMAC as $val) {
          $tmp = dechex($val);
          $tmp = str_pad($tmp, 2, "0", STR_PAD_LEFT);
          $hexMAC[$h++] = $tmp;
        }
        return implode(':',$hexMAC);
    }

    private function niceMacHex($mac) {
        $arrMAC = explode(":",$mac);
        $hexMAC = array();
        $h = 0;
        foreach ($arrMAC as $val) {
          $tmp = str_pad($val, 2, "0", STR_PAD_LEFT);
          $hexMAC[$h++] = $tmp;
        }
        return implode(':',$hexMAC);

    }

    private function ciscoSSID($ssid) {
       $tmp = explode(" ",$ssid);
       array_pop($tmp);
       array_pop($tmp);
       $ascii = array();
       $a = 0;
       foreach ($tmp as $t) {
         $ascii[$a++] = chr(hexdec($t));
       }
       return implode('',$ascii);
    }

    private function hex2ip($ip) {
       $tmp = explode(" ",$ip);
       array_pop($tmp);
       #array_pop($tmp);
       $ascii = array();
       $a = 0;
       foreach ($tmp as $t) {
         $ascii[$a++] = hexdec($t);
       }
       return implode('.',$ascii);
    }

    private function mask2cidr($mask){
      $long = ip2long($mask);
      $base = ip2long('255.255.255.255');
      return 32-log(($long ^ $base)+1,2);
    }

 
    private function cidr_match($ip, $range)
    {
       list ($subnet, $bits) = explode('/', $range);
       $ip = ip2long($ip);
       $subnet = ip2long($subnet);
       $mask = -1 << (32 - $bits);
       $subnet &= $mask; # nb: in case the supplied subnet wasn't correctly aligned
       return ($ip & $mask) == $subnet;
    }

    private function hexIPtoDecIP($IP) {
        $arrIP = explode(" ",$IP);
        $decIP = array();
        $h = 0;
        foreach ($arrIP as $val) {
          if ($h > 3) { continue; }
          $tmp = hexdec($val);
          $hexMAC[$h++] = $tmp;
        }
        return implode('.',$hexMAC);

     }



    ///////////////////////////////////////////
    // Private status/diagnostics functions  //
    ///////////////////////////////////////////


    private function docsIfCmtsCmStatusValue($status) {
        $Status = array(
                1 => 'other',
                2 => 'ranging',
                3 => 'rangingAborted',
                4 => 'rangingComplete',
                5 => 'ipComplete',
                6 => 'registrationComplete',
                7 => 'accessDenied'
                );
       return $Status[$status];

    }


    private function WlanSecurity($status) {
        $Status = array(
	      0 => 'disabled',
              1 => 'wep',
              2 => 'wpaPsk',
              3 => 'wpa2Psk',
              4 => 'wpaEnterprise',
              5 => 'wpa2Enterprise',
              7 => 'wpaWpa2Psk',
              8 => 'wpaWpa2Enterprise',
	     23 => 'wpaPskwpa2Psk'
              );
      return $Status[$status];
    }

    private function reverseWlanSecurity($status) {
        $Status = array(
	      0 => 'disabled',
              1 => 'wep',
              2 => 'wpaPsk',
              3 => 'wpa2Psk',
              4 => 'wpaEnterprise',
              5 => 'wpa2Enterprise',
              7 => 'wpaWpa2Psk',
              8 => 'wpaWpa2Enterprise',
	     23 => 'wpaPskwpa2Psk'
              );	
	      $Status = array_flip($Status);
      return $Status[$status];
    }


    private function docsIf3CmtsCmUsStatusRangingStatus($status) {
        $Status = array(
                1 => 'other',
                2 => 'aborted',
                3 => 'retriesExceeded',
                4 => 'success',
                5 => 'continue',
                6 => 'timeoutT4'
                );
       return $Status[$status];

    }

    private function docsIf3CmtsCmUsStatusModulationType($status) {
        $Status = array(
                0 => 'unknown',
                1 => 'tdma',
                2 => 'atdma',
                3 => 'scdma',
                4 => 'tdmaAndAtdma'
                );
       return $Status[$status];

    }

    private function docsIfDownChannelModulation($status) {
        $Status = array(
                1 => 'unknown',
                2 => 'other',
                3 => 'qam64',
                4 => 'qam256'
                );
       return $Status[$status];

    }

    private function docsIfDownChannelAnnex($status) {
        $Status = array(
                1 => 'unknown',
                2 => 'other',
                3 => 'annexA',
                4 => 'annexB',
                5 => 'annexC'
                );
       return $Status[$status];

    }

    private function enumerateHostOid($i) {

        $soid = array(
                3 => 'IPAddress',
                4 => 'MACAddress',
                5 => 'undef',
                6 => 'LeaseTimeRemaining',
                7 => 'HostName',
                8 => 'undef',
                9 => 'Interface');

       return $soid[$i];

    }

    private function CiscoAPSTable($index) {
  	$APSTable = array(
        	1 => 'Valid',
        	2 => 'NetworkName',
        	3 => 'SecurityMode',
        	4 => 'PhyMode',
       		5 => 'Rssi',
        	6 => 'Channel',
        	7 => 'MacAddress',
        	8 => 'ChannelWidth');

	return $APSTable[$index];

    }

    private function clearSNMPResult($val) {

	$val = eregi_replace("IpAddress: ","",$val);
   	$val = eregi_replace("GAUGE32: ","",$val);
   	$val = eregi_replace("INTEGER: ","",$val);
   	$val = eregi_replace("Hex-STRING: ","",$val);
   	$val = eregi_replace("STRING: ","",$val);
   	return $val;
    }

    private function snmpDateAndTime( $hexstring ) {
	$hexstring = eregi_replace(" ","",$hexstring);
  	$date = "";
  	$date = str_pad(hexdec(substr( $hexstring, 6, 2 )), 2, "0", STR_PAD_LEFT) . "." .  str_pad(hexdec(substr( $hexstring, 4, 2 )), 2, "0", STR_PAD_LEFT) . "." . str_pad(hexdec(substr( $hexstring, 0, 4 )), 2, "0", STR_PAD_LEFT) . " " . str_pad(hexdec(substr( $hexstring, 8, 2 )), 2, "0", STR_PAD_LEFT) . ":" . str_pad(hexdec(substr( $hexstring, 10, 2 )), 2, "0", STR_PAD_LEFT) . ":" . str_pad(hexdec(substr( $hexstring, 12, 2 )), 2, "0", STR_PAD_LEFT);
  	return $date;
    }



   private function getCMTS() {

      $CMTSa = array();
      $string = file_get_contents("http://modemdiag.cmtsnet.local/cmtses.json");
      $cmtses = json_decode($string, true);
      $cmtsarr = $cmtses['CMTS'];
      #var_dump($cmtsarr);
      foreach ($cmtsarr as $k => $v) {
        var_dump($k);
        $CMTSa[$k]['IP'] = $v['hostname'];
        $CMTSa[$k]['name'] = $v['name'];
        $CMTSa[$k]['snmpCommunity'] = $v['community'];
        $CMTSa[$k]['vendor'] = $v['vendor'];
      }
      return $CMTSa;      

   }

   private function myErrorHandler($errno , $errstr){
	throw new MyException($errstr, $errno);
   }



}

$server = new SoapServer("http://modemdiag.cmtsnet.local/ws/DocsisCMDiag.wsdl");


$server->setClass('MyAPI');

$server->handle();
?>
