<?php
/**
 * Author: Tom Witkowski
 * Author URI: https://github.com/Gummibeer
 * Copyright 2014 Tom Witkowski (email : dev.gummibeer@gmail.com)
 * License: GPL2
 */

class similar_content extends wp_suchanalyse {
    private $keywords;

    private $posts;
    private $categories;
    private $tags;

    public function __construct( $query ) {
        parent::__construct();

        $this->keywords = $this->explode_keywords( strtolower( $query ) );

        $this->posts = false;
        $this->categories = false;
        $this->tags = false;
    }

    private function get_posts() {
        wp_reset_query();
        wp_reset_postdata();
        $args =	array(
            'numberposts' => -1,
            'post_type' => 'post',
            'post_status' => 'publish',

        );
        $the_query = new WP_Query( $args );

        if($the_query->have_posts()) :
            $this->posts = array();

            while($the_query->have_posts()) : $the_query->the_post();
                $cur_id = get_the_ID();
                $cur_keywords = get_post_meta( $cur_id, 'similar_keywords', true );
                $cur_title = get_the_title();
                $cur_content = get_the_content();
                $cur_excerpt = get_the_excerpt();

                if( !is_int($this->posts[$cur_id]) ) {
                    $this->posts[$cur_id] = 0;
                }

                foreach( $this->keywords as $keyword ) {
                    // exact Matches
                    preg_match( '/'.$keyword.'/i', $cur_keywords, $keywords_matches );
                    preg_match( '/'.$keyword.'/i', $cur_title, $title_matches );
                    preg_match( '/'.$keyword.'/i', $cur_content, $content_matches );
                    preg_match( '/'.$keyword.'/i', $cur_excerpt, $excerpt_matches );
                    if( count($keywords_matches) > 0 ) {
                        $this->posts[$cur_id] = $this->posts[$cur_id] + 5;
                    }
                    if( count($title_matches) > 0 ) {
                        $this->posts[$cur_id] = $this->posts[$cur_id] + 3;
                    }
                    if( count($content_matches) > 0 ) {
                        $this->posts[$cur_id] = $this->posts[$cur_id] + 2;
                    }
                    if( count($excerpt_matches) > 0 ) {
                        $this->posts[$cur_id] = $this->posts[$cur_id] + 1;
                    }

                    // similar
                    $length = strlen($keyword);
                    foreach( $this->explode_keywords($cur_keywords) as $cur_keywords_word ) {
                        $keywords_similar = 1 - ( levenshtein( $keyword, $cur_keywords_word ) / $length );
                        $keywords_similar = $keywords_similar < 0 ? 0 : $keywords_similar;
                        $this->posts[$cur_id] = $this->posts[$cur_id] + $keywords_similar;
                    }

                    foreach( $this->explode_keywords($cur_title) as $cur_title_word ) {
                        $title_similar = 1 - ( levenshtein( $keyword, $cur_title_word ) / $length );
                        $title_similar = $title_similar < 0 ? 0 : $title_similar;
                        $this->posts[$cur_id] = $this->posts[$cur_id] + $title_similar;
                    }

                    foreach( $this->explode_keywords($cur_excerpt) as $cur_excerpt_word ) {
                        $excerpt_similar = 1 - ( levenshtein( $keyword, $cur_excerpt_word ) / $length );
                        $excerpt_similar = $excerpt_similar < 0 ? 0 : $excerpt_similar;
                        $this->posts[$cur_id] = $this->posts[$cur_id] + $excerpt_similar;
                    }
                }

            endwhile;
            arsort($this->posts);
        endif;
        wp_reset_query();
        wp_reset_postdata();
    }

    private function get_categories() {
        $categories = get_categories( );

        foreach( $categories as $category ) {
                $cur_id = $category->term_id;
                $cur_title = $category->name;
                $cur_description = $category->description;

                if( !is_int($this->categories[$cur_id]) ) {
                    $this->categories[$cur_id] = 0;
                }

                foreach( $this->keywords as $keyword ) {
                    // exact Matches
                    preg_match( '/'.$keyword.'/i', $cur_title, $title_matches );
                    preg_match( '/'.$keyword.'/i', $cur_description, $description_matches );
                    if( count($title_matches) > 0 ) {
                        $this->categories[$cur_id] = $this->categories[$cur_id] + 2;
                    }
                    if( count($description_matches) > 0 ) {
                        $this->categories[$cur_id] = $this->categories[$cur_id] + 1;
                    }

                    // similar
                    $length = strlen($keyword);
                    foreach( $this->explode_keywords($cur_title) as $cur_title_word ) {
                        $title_similar = 1 - ( levenshtein( $keyword, $cur_title_word ) / $length );
                        $title_similar = $title_similar < 0 ? 0 : $title_similar;
                        $this->categories[$cur_id] = $this->categories[$cur_id] + $title_similar;
                    }

                    foreach( $this->explode_keywords($cur_description) as $cur_description_word ) {
                        $description_similar = 1 - ( levenshtein( $keyword, $cur_description_word ) / $length );
                        $description_similar = $description_similar < 0 ? 0 : $description_similar;
                        $this->categories[$cur_id] = $this->categories[$cur_id] + $description_similar;
                    }
                }

            arsort($this->categories);
        }
    }

    private function get_tags() {
        $tags = get_tags( );

        foreach( $tags as $tag ) {
            $cur_id = $tag->term_id;
            $cur_title = $tag->name;
            $cur_description = $tag->description;

            if( !is_int($this->tags[$cur_id]) ) {
                $this->tags[$cur_id] = 0;
            }

            foreach( $this->keywords as $keyword ) {
                // exact Matches
                preg_match( '/'.$keyword.'/i', $cur_title, $title_matches );
                preg_match( '/'.$keyword.'/i', $cur_description, $description_matches );
                if( count($title_matches) > 0 ) {
                    $this->tags[$cur_id] = $this->tags[$cur_id] + 2;
                }
                if( count($description_matches) > 0 ) {
                    $this->tags[$cur_id] = $this->tags[$cur_id] + 1;
                }

                // similar
                $length = strlen($keyword);
                foreach( $this->explode_keywords($cur_title) as $cur_title_word ) {
                    $title_similar = 1 - ( levenshtein( $keyword, $cur_title_word ) / $length );
                    $title_similar = $title_similar < 0 ? 0 : $title_similar;
                    $this->tags[$cur_id] = $this->tags[$cur_id] + $title_similar;
                }

                foreach( $this->explode_keywords($cur_description) as $cur_description_word ) {
                    $description_similar = 1 - ( levenshtein( $keyword, $cur_description_word ) / $length );
                    $description_similar = $description_similar < 0 ? 0 : $description_similar;
                    $this->tags[$cur_id] = $this->tags[$cur_id] + $description_similar;
                }
            }

            arsort($this->tags);
        }
    }


    /*
     * public
     */
    /**
     * @param int $min_rank
     * @return array
     */
    public function get_posts_id( $min_rank = 0 ) {
        $posts = false;

        if( $this->posts === false ) {
            $this->get_posts();
        }

        if( $this->posts !== false ) {
            $posts = array();
            foreach( $this->posts as $key => $value ) {
                if( $value > $min_rank ) {
                    $posts[$key] = $value;
                }
            }
        }

        return $posts;
    }

    /**
     * @param int $min_rank
     * @return array
     */
    public function get_categories_id( $min_rank = 0 ) {
        $categories = false;

        if( $this->categories === false ) {
            $this->get_categories();
        }

        if( $this->categories !== false ) {
            $categories = array();
            foreach( $this->categories as $key => $value ) {
                if( $value > $min_rank ) {
                    $categories[$key] = $value;
                }
            }
        }

        return $categories;
    }

    /**
     * @param int $min_rank
     * @return array
     */
    public function get_tags_id( $min_rank = 0 ) {
        $tags = false;

        if( $this->tags === false ) {
            $this->get_tags();
        }

        if( $this->tags !== false ) {
            $tags = array();
            foreach( $this->tags as $key => $value ) {
                if( $value > $min_rank ) {
                    $tags[$key] = $value;
                }
            }
        }

        return $tags;
    }
}