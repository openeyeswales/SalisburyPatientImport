<?php

/**
 * OpenEyes
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2012
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2008-2011, Moorfields Eye Hospital NHS Foundation Trust
 * @copyright Copyright (c) 2011-2012, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */
class PatientDataCommand extends CConsoleCommand {

    private $importedRecords = 0;
    
    public function getHelp() {
        return "Usage:\n\n\tpatientdata import\n";

    }

    /**
     * Take a list of real patient identifiers that appear in a collection
     * of FMES files, and remove the 'real' PID in the FMES file in favour
     * of 
     * 
     * @param type $realPidFile
     * @param type $anonPidFile 
     */
    public function actionImport($file) {
        $row = 0;
        if (($handle = fopen($file, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $num = count($data);
                $row++;
                if (count($data) == 14) {
                    $this->importPatient($row, $data);
                } else {
                    echo "Failed to import row " . $row . "; contained invalid column count of " . count($data) . "\n";
                }
            }
            fclose($handle);
        }
        echo "Rows: " . $row . "; successfully imported records: " . $this->importedRecords . "\n";
    }

    /**
     * 
     * @param type $data
     * @return boolean
     */
    private function importPatient($row, $data) {
        $contact = $this->getContact($row, $data);
        if ($contact) {
            $patient = $this->getPatient($row, $data);
            echo $patient->hos_num . "\n";
            $exists = Patient::model()->find("hos_num = :hos_num", array(":hos_num" => $patient->hos_num));
            if ($exists) {
                echo "Patient " . $patient->hos_num . " already exists; continuing...\n";
                return;
            }
            $address = $this->getAddress($row, $data);
            $address->contact_id = $contact->id;
            if (!$contact->save()) {
                echo "Failed to insert contact data for " . $row . "\n";
                return;
            }
            if ($patient) {
                $patient->contact_id = $contact->id;
            }
            if (!isset($patient) || !$patient->save()) {
                $contact->delete();
                echo "Failed to insert patient data for " . $row . "\n";
                return;
            }
            if ($address) {
                $address->contact_id = $contact->id;
            }
            if (!isset($address) || !$address->save()) {
                $contact->delete();
                $patient->delete();
                echo "Failed to insert address for " . $row . "\n";
                return;
            }
            $this->importedRecords++;
        }
    }

    /**
     * 
     * @param type $row
     * @param type $data
     * @return null|\Address
     */
    private function getAddress($row, $data) {
        $address = new Address();
        if (!empty($data[9])) {
            $address->address1 = $data[9];
        } else {
            $this->error($row, "address 1");
            return null;
        }
        if (!empty($data[10])) {
            $address->address2 = $data[10];
        }
        if (!empty($data[11])) {
            $address->city = $data[11];
        } else {
            $this->error($row, "city");
            return null;
        }
        if (!empty($data[12])) {
            $address->postcode = $data[12];
        } else {
            $this->error($row, "postcode");
            return null;
        }
        if (!empty($data[13])) {
            $address->county = $data[13];
        }
        $address->country_id = 1;

        return $address;
    }

    /**
     * 
     * @param type $row
     * @param type $data
     * @return \Patient|null
     */
    private function getPatient($row, $data) {
        $patient = new Patient();
        if (!empty($data[0])) {
            $patient->pas_key = $data[0];
        }
        if (!empty($data[1])) {
            $patient->dob = $data[1];
        } else {
            $this->error($row, "DoB");
            return null;
        }
        if (!empty($data[2])) {
            $patient->gender = $data[2];
        }
        if (!empty($data[3])) {
            $patient->hos_num = $data[3];
        } else {
            $this->error($row, "hospital number");
            return null;
        }
        if (!empty($data[4])) {
            $patient->nhs_num = $data[4];
        }
        return $patient;
    }

    /**
     * 
     * @param type $row
     * @param type $data
     * @return null|\Contact
     */
    private function getContact($row, $data) {
        $contact = new Contact();
        if (!empty($data[8])) {
            $contact->primary_phone = $data[8];
        }
        if (!empty($data[5])) {
            $contact->title = $data[5];
        }
        if (!empty($data[6])) {
            $contact->first_name = $data[6];
        } else {
            $this->error($row, "first name");
            return null;
        }
        if (!empty($data[7])) {
            $contact->last_name = $data[7];
        } else {
            $this->error($row, "last name");
            return null;
        }
        return $contact;
    }

    /**
     * 
     * @param type $row
     * @param type $field
     */
    private function error($row, $field) {
        echo "Error: '" . $field . "'  not defined, row: " . $row . "\n";
    }

}
