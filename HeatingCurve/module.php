<?php
class TileVisuHeatingCurveTile extends IPSModule
{
    public function Create()
    {
        // Nie diese Zeile löschen!
        parent::Create();

        // Drei Eigenschaften für die dargestellten Zähler
        $this->RegisterPropertyInteger("bgImage", 0);
        $this->RegisterPropertyFloat('Schriftgroesse', 1);
        $this->RegisterPropertyFloat('Bildtransparenz', 0.7);
        $this->RegisterPropertyInteger('Kachelhintergrundfarbe', 0x000000);
        $this->RegisterPropertyInteger('Schriftfarbe', 0xFFFFFF);
        $this->RegisterPropertyFloat('MinVorlauf', 20);
        $this->RegisterPropertyFloat('MaxVorlauf', 50);
        $this->RegisterPropertyFloat('MinAussentemperatur', -10);
        $this->RegisterPropertyFloat('MaxAussentemperatur', 15);

        // Visualisierungstyp auf 1 setzen, da wir HTML anbieten möchten
        $this->SetVisualizationType(1);
        
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

                //Referenzen Registrieren
                $ids = [
                    $this->ReadPropertyInteger('bgImage'),
                ];
                $refs = $this->GetReferenceList();
                    foreach($refs as $ref) {
                        $this->UnregisterReference($ref);
                    } 
                    foreach ($ids as $id) {
                        if ($id !== '') {
                            $this->RegisterReference($id);
                        }
                    }

        // Aktualisiere registrierte Nachrichten
        foreach ($this->GetMessageList() as $senderID => $messageIDs)
        {
            foreach ($messageIDs as $messageID)
            {
                $this->UnregisterMessage($senderID, $messageID);
            }
        }


        foreach (['bgImage'] as $VariableProperty)        {
            $this->RegisterMessage($this->ReadPropertyInteger($VariableProperty), VM_UPDATE);
        }

        // Schicke eine komplette Update-Nachricht an die Darstellung, da sich ja Parameter geändert haben können
        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {

        foreach (['bgImage'] as $index => $VariableProperty)
        {
            if ($SenderID === $this->ReadPropertyInteger($VariableProperty))
            {
                switch ($Message)
                {
                    case VM_UPDATE:
                        
                        // Teile der HTML-Darstellung den neuen Wert mit. Damit dieser korrekt formatiert ist, holen wir uns den von der Variablen via GetValueFormatted
                        $this->UpdateVisualizationValue(json_encode(['variable' => GetValueFormatted($this->ReadPropertyInteger($VariableProperty))]));
                        break;
                }
            }
        }
    }

    public function GetVisualizationTile()
    {
        // Füge ein Skript hinzu, um beim Laden, analog zu Änderungen bei Laufzeit, die Werte zu setzen
        $initialHandling = '<script>handleMessage(' . json_encode($this->GetFullUpdateMessage()) . ')</script>';

        $settings = '<script type="text/javascript">';
        $settings .= 'var minvorlauf = ' . $this->ReadPropertyFloat('MinVorlauf') . ';';
        $settings .= 'var maxvorlauf = ' . $this->ReadPropertyFloat('MaxVorlauf') . ';';
        $settings .= 'var minaussentemperatur = ' . $this->ReadPropertyFloat('MinAussentemperatur') . ';';
        $settings .= 'var maxaussentemperatur = ' . $this->ReadPropertyFloat('MaxAussentemperatur') . ';';
        $settings .= '</script>';

        // Füge statisches HTML aus Datei hinzu
        $module = file_get_contents(__DIR__ . '/module.html');

        // Gebe alles zurück.
        // Wichtig: $initialHandling nach hinten, da die Funktion handleMessage erst im HTML definiert wird
        return $settings . $module . $initialHandling;
    }

    // Generiere eine Nachricht, die alle Elemente in der HTML-Darstellung aktualisiert
    private function GetFullUpdateMessage()
    {
        $result['fontsize'] = $this->ReadPropertyFloat('Schriftgroesse');
        $result['hintergrundfarbe'] = '#' . sprintf('%06X', $this->ReadPropertyInteger('Kachelhintergrundfarbe'));
        $result['schriftfarbe'] = '#' . sprintf('%06X', $this->ReadPropertyInteger('Schriftfarbe'));
        $result['transparenz'] = $this->ReadPropertyFloat('Bildtransparenz');
        $result['minvorlauf'] = $this->ReadPropertyFloat('MinVorlauf');
        $result['maxvorlauf'] = $this->ReadPropertyFloat('MaxVorlauf');
        $result['minaussentemperatur'] = $this->ReadPropertyFloat('MinAussentemperatur');
        $result['maxaussentemperatur'] = $this->ReadPropertyFloat('MaxAussentemperatur');


            // Prüfe vorweg, ob ein Bild ausgewählt wurde
            $imageID = $this->ReadPropertyInteger('bgImage');
            if (IPS_MediaExists($imageID))
            {
                $image = IPS_GetMedia($imageID);
                if ($image['MediaType'] === MEDIATYPE_IMAGE)
                {
                    $imageFile = explode('.', $image['MediaFile']);
                    $imageContent = '';
                    // Falls ja, ermittle den Anfang der src basierend auf dem Dateitypen
                    switch (end($imageFile))
                    {
                        case 'bmp':
                            $imageContent = 'data:image/bmp;base64,';
                            break;

                        case 'jpg':
                        case 'jpeg':
                            $imageContent = 'data:image/jpeg;base64,';
                            break;

                        case 'gif':
                            $imageContent = 'data:image/gif;base64,';
                            break;

                        case 'png':
                            $imageContent = 'data:image/png;base64,';
                            break;

                        case 'ico':
                            $imageContent = 'data:image/x-icon;base64,';
                            break;
                    }

                    // Nur fortfahren, falls Inhalt gesetzt wurde. Ansonsten ist das Bild kein unterstützter Dateityp
                    if ($imageContent)
                    {
                        // Hänge base64-codierten Inhalt des Bildes an
                        $imageContent .= IPS_GetMediaContent($imageID);
                        $result['image1'] = $imageContent;
                    }
                }
            }
            else
            {
                $imageContent = 'data:image/png;base64,';
                $imageContent .= base64_encode(file_get_contents(__DIR__ . '/assets/placeholder.png'));
                $result['image1'] = $imageContent;
            }

        return json_encode($result);
    }
}
?>
