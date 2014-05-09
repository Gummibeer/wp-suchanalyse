<?php
/**
 * Author: Tom Witkowski
 * Author URI: https://github.com/Gummibeer
 * Copyright: 2014 Tom Witkowski (email: dev.gummibeer@gmail.com)
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

                $title_words = str_word_count( preg_replace( '/[^a-z0-9]/i', ' ', $cur_title ) );
                $content_words = str_word_count( preg_replace( '/[^a-z0-9]/i', ' ', $cur_content ) );
                $excerpt_words = str_word_count( preg_replace( '/[^a-z0-9]/i', ' ', $cur_excerpt ) );

                if( !is_int($this->posts[$cur_id]) ) {
                    $this->posts[$cur_id] = 0;
                }

                foreach( $this->keywords as $keyword ) {
                    // exact matches
                    preg_match_all( '/ '.$keyword.' /i', ' '.$cur_keywords.' ', $keywords_matches );
                    preg_match_all( '/ '.$keyword.' /i', ' '.$cur_title.' ', $title_matches );
                    preg_match_all( '/ '.$keyword.' /i', ' '.$cur_content.' ', $content_matches );
                    preg_match_all( '/ '.$keyword.' /i', ' '.$cur_excerpt.' ', $excerpt_matches );

                    $keywords_matches = count($keywords_matches[0]);
                    $title_matches = count($title_matches[0]);
                    $content_matches = count($content_matches[0]);
                    $excerpt_matches = count($excerpt_matches[0]);

                    if( $keywords_matches > 0 ) {
                        $this->posts[$cur_id] = $this->posts[$cur_id] + 5;
                    }
                    if( $title_matches > 0 ) {
                        $density = $title_words / $title_matches;
                        $density = $density > 1 ? 1 : $density;
                        $density++;
                        $this->posts[$cur_id] = $this->posts[$cur_id] + (3 * $density);
                    }
                    if( $content_matches > 0 ) {
                        $density = $content_words / $content_matches;
                        $density = $density > 1 ? 1 : $density;
                        $density++;
                        $this->posts[$cur_id] = $this->posts[$cur_id] + (2 * $density);
                    }
                    if( $excerpt_matches > 0 ) {
                        $density = $excerpt_words / $excerpt_matches;
                        $density = $density > 1 ? 1 : $density;
                        $density++;
                        $this->posts[$cur_id] = $this->posts[$cur_id] + (1 * $density);
                    }

                    // part Matches
                    preg_match_all( '/'.$keyword.'/i', $cur_keywords, $keywords_matches );
                    preg_match_all( '/'.$keyword.'/i', $cur_title, $title_matches );
                    preg_match_all( '/'.$keyword.'/i', $cur_content, $content_matches );
                    preg_match_all( '/'.$keyword.'/i', $cur_excerpt, $excerpt_matches );

                    $keywords_matches = count($keywords_matches[0]);
                    $title_matches = count($title_matches[0]);
                    $content_matches = count($content_matches[0]);
                    $excerpt_matches = count($excerpt_matches[0]);

                    if( $keywords_matches > 0 ) {
                        $this->posts[$cur_id] = $this->posts[$cur_id] + 5;
                    }
                    if( $title_matches > 0 ) {
                        $density = $title_words / $title_matches;
                        $density = $density > 1 ? 1 : $density;
                        $density++;
                        $this->posts[$cur_id] = $this->posts[$cur_id] + (3 * $density);
                    }
                    if( $content_matches > 0 ) {
                        $density = $content_words / $content_matches;
                        $density = $density > 1 ? 1 : $density;
                        $density++;
                        $this->posts[$cur_id] = $this->posts[$cur_id] + (2 * $density);
                    }
                    if( $excerpt_matches > 0 ) {
                        $density = $excerpt_words / $excerpt_matches;
                        $density = $density > 1 ? 1 : $density;
                        $density++;
                        $this->posts[$cur_id] = $this->posts[$cur_id] + (1 * $density);
                    }

                    // similar matches
                    $length = strlen($keyword);
                    $this->posts[$cur_id] = $this->posts[$cur_id] + $this->calc_levenshtein( $cur_keywords, $keyword, $length );
                    $this->posts[$cur_id] = $this->posts[$cur_id] + $this->calc_levenshtein( $cur_title, $keyword, $length );
                    $this->posts[$cur_id] = $this->posts[$cur_id] + $this->calc_levenshtein( $cur_excerpt, $keyword, $length );

                    $this->posts[$cur_id] = $this->posts[$cur_id] + $this->calc_misspelling( $keyword, $cur_keywords );
                    $this->posts[$cur_id] = $this->posts[$cur_id] + $this->calc_misspelling( $keyword, $cur_title );
                    $this->posts[$cur_id] = $this->posts[$cur_id] + $this->calc_misspelling( $keyword, $cur_excerpt );
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
                    $this->categories[$cur_id] = $this->categories[$cur_id] + $this->calc_levenshtein( $cur_title, $keyword, $length );
                    $this->categories[$cur_id] = $this->categories[$cur_id] + $this->calc_levenshtein( $cur_description, $keyword, $length );

                    $this->categories[$cur_id] = $this->categories[$cur_id] + $this->calc_misspelling( $keyword, $cur_title );
                    $this->categories[$cur_id] = $this->categories[$cur_id] + $this->calc_misspelling( $keyword, $cur_description );
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
                $this->tags[$cur_id] = $this->tags[$cur_id] + $this->calc_levenshtein( $cur_title, $keyword, $length );
                $this->tags[$cur_id] = $this->tags[$cur_id] + $this->calc_levenshtein( $cur_description, $keyword, $length );

                $this->tags[$cur_id] = $this->tags[$cur_id] + $this->calc_misspelling( $keyword, $cur_title );
                $this->tags[$cur_id] = $this->tags[$cur_id] + $this->calc_misspelling( $keyword, $cur_description );
            }

            arsort($this->tags);
        }
    }



    private function calc_levenshtein( $cur_text, $keyword, $length ) {
        $return = 0;
        $cur_text = preg_replace( '/[^a-z0-9äöüß]/i', ' ', $cur_text );
        foreach( $this->explode_keywords($cur_text) as $cur_text_word ) {
            $similar = 1 - ( levenshtein( $keyword, $cur_text_word ) / $length );
            $similar = $similar < 0 ? 0 : $similar;
            $similar = $similar < 0.25 ? 0 : $similar;
            $similar = $similar >= 0.5 ? $similar * 2 : $similar;
            $return += $similar;
        }
        $return = round( $return, 4 );
        return $return;
    }

    private function calc_misspelling( $word1, $text ) {
        $return = 0;
        $text = preg_replace( '/[^a-z0-9äöüß]/i', ' ', $text );

        foreach( $this->explode_keywords($text) as $word2 ) {
            $w1_len  = strlen($word1);
            $w2_len  = strlen($word2);
            $score  += $w1_len > $w2_len ? $w1_len - $w2_len : $w2_len - $w1_len;

            $w1 = $w1_len > $w2_len ? $word1 : $word2;
            $w2 = $w1_len > $w2_len ? $word2 : $word1;

            for($i=0; $i < strlen($w1); $i++) {
                if ( !isset($w2[$i]) || $w1[$i] != $w2[$i] ) {
                    $score++;
                }
            }

            $score = 10 - $score;
            $score = $score < 0 ? 0 : $score;
            $score = $score <= 5 ? 0 : $score;
            $score = $score > 5 ? 1 : 0;
            $return += $score;
        }

        return $return;
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