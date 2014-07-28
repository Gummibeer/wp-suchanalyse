<?php
/*
 * just copy and paste this example code where you want the output
 * you can replace the search-keyword-param 'get_search_query(false)' by a fix value or your own function
 */
$test = new similar_content( get_search_query(false), false );
$similar_posts = $test->get_posts_id();
$similar_categories = $test->get_categories_id();
$similar_tags = $test->get_tags_id();

if( $similar_posts !== false && !empty($similar_posts) ) {
    echo 'Beiträge: ';
    foreach( $similar_posts as $key => $value ) {
        echo '<a href="'.get_the_permalink($key).'">'.get_the_title($key).'</a> ('.$value.')';
    }
    echo '<br>';
}

if( $similar_categories !== false && !empty($similar_categories) ) {
    echo 'Kategorien: ';
    foreach( $similar_categories as $key => $value ) {
        echo '<a href="'.get_category_link($key).'">'.get_cat_name($key).'</a> ('.$value.')';
    }
    echo '<br>';
}

if( $similar_tags !== false && !empty($similar_tags) ) {
    echo 'Schlagworte: ';
    foreach( $similar_tags as $key => $value ) {
        echo '<a href="'.get_tag_link($key).'">'.get_tag($key)->name.'</a> ('.$value.')';
    }
    echo '<br>';
}
?>