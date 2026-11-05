<?php
return [
    'message' => [
        'created'     => 'Dein API-Schlüssel wurde erstellt. Kopiere ihn jetzt, er wird nur einmal angezeigt.',
        'regenerated' => 'Dein API-Schlüssel wurde neu erzeugt. Der alte Schlüssel funktioniert nicht mehr. Kopiere den neuen jetzt, er wird nur einmal angezeigt.',
        'revoked'     => 'Dein API-Schlüssel wurde gesperrt.',
        'deleted'     => 'Dein API-Schlüssel wurde gelöscht.',
        'exists'      => 'Du hast bereits einen API-Schlüssel.',
        'missing'     => 'Du hast keinen API-Schlüssel.',
    ],

    'keys' => [
        'sidename'   => 'API-Schlüssel',
        'title'      => 'Dein API-Schlüssel',
        'intro'      => 'Mit dem Schlüssel kann ein Programm in deinem Namen auf xTIPP zugreifen (Spiele, Tipps, Ranglisten, Tipps ändern). Du kannst einen Schlüssel haben. Sende ihn bei jedem Aufruf im Header „Authorization: Bearer &lt;Schlüssel&gt;“.',
        'warning'    => 'Behandle den Schlüssel wie ein Passwort: Wer ihn kennt, kann in deinem Namen tippen. Er gilt, bis du ihn sperrst, löschst oder neu erzeugst.',
        'docs'       => 'Dokumentation und Test der Schnittstelle (Swagger)',
        'new-title'  => 'Dein neuer Schlüssel',
        'new-hint'   => 'Dieser Schlüssel wird nur jetzt angezeigt. Danach ist nur noch der Anfang sichtbar.',
        'none'       => 'Du hast noch keinen API-Schlüssel.',
        'key'        => 'Schlüssel',
        'created'    => 'Erstellt',
        'last-used'  => 'Zuletzt verwendet',
        'never'      => 'Noch nie',
        'expires'    => 'Gültig bis',
        'unlimited'  => 'Unbegrenzt',
        'expires-hint' => 'Optional. Ohne Datum läuft der Schlüssel nicht ab.',
        'status'     => 'Status',
        'state'      => [
            'active'  => 'Aktiv',
            'revoked' => 'Gesperrt',
            'expired' => 'Abgelaufen',
        ],
        'btn' => [
            'create'     => 'Schlüssel erstellen',
            'regenerate' => 'Neu erzeugen',
            'revoke'     => 'Sperren',
            'delete'     => 'Löschen',
            'copy'       => 'Kopieren',
        ],
        'confirm' => [
            'regenerate' => 'Der alte Schlüssel funktioniert danach nicht mehr. Programme, die ihn verwenden, müssen den neuen Schlüssel erhalten.',
            'revoke'     => 'Der Schlüssel funktioniert danach nicht mehr. Du kannst ihn später neu erzeugen.',
            'delete'     => 'Der Schlüssel wird gelöscht und funktioniert danach nicht mehr. Du kannst anschließend einen neuen erstellen.',
        ],
    ],
];
