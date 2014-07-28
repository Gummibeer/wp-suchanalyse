Wordpress Suchanalyse
==============

Inhaltsverzeichnis
----------------------
1. Suchanalyse
    1. Einstellungen
    2. Screenshots
2. Ähnlichkeitssuche *beta*
    1. Einbindung
    2. Diskussion

Suchanalyse
----------------------

### Einstellungen
**Suchwörter blockieren**
Es können beliebige Suchwörter blockiert werden - diese werden nicht in den Einzelsuchwort-Statistiken dargestellt und in den Gesamtsuchanfragen ausgeblendet/durchgestrichen.

**Suchanfragen löschen**
Es können einzelne Suchanfragen gelöscht werden - diese werden im gesamten gelöscht und rekursiv aus den Einzelsuchwort-Statistiken heraus gerechnet.

**alle Daten löschen**
Es können alle gesammelten Daten gelöscht werden - z.B. nach Seitenrelaunch.


### Screenshots

Ähnlichkeitssuche
----------------------

### Einbindung
Dieser php-Code Block kann an jeder beliebigen Stelle eingebaut und angepasst werden:
```php
<?php
// erzeugen des Objektes
$test = new similar_content( get_search_query(false), false );
// ähnliche Beiträge, Kategorien, Schlagworte holen
$similar_posts = $test->get_posts_id();
$similar_categories = $test->get_categories_id();
$similar_tags = $test->get_tags_id();

// ähnliche Beiträge ausgeben
if( $similar_posts !== false && !empty($similar_posts) ) {
    echo 'Beiträge: ';
    foreach( $similar_posts as $key => $value ) {
        echo '<a href="'.get_the_permalink($key).'">'.get_the_title($key).'</a> ('.$value.')';
    }
    echo '<br>';
}

// ähnliche Kategorien ausgeben
if( $similar_categories !== false && !empty($similar_categories) ) {
    echo 'Kategorien: ';
    foreach( $similar_categories as $key => $value ) {
        echo '<a href="'.get_category_link($key).'">'.get_cat_name($key).'</a> ('.$value.')';
    }
    echo '<br>';
}

// ähnliche Schlagworte ausgeben
if( $similar_tags !== false && !empty($similar_tags) ) {
    echo 'Schlagworte: ';
    foreach( $similar_tags as $key => $value ) {
        echo '<a href="'.get_tag_link($key).'">'.get_tag($key)->name.'</a> ('.$value.')';
    }
    echo '<br>';
}
?>
```


### Diskussion