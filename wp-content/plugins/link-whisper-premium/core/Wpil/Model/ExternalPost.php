<?php

/**
 * Model for external site data items.
 * Since the external data is used in post like situations,
 * we need an object that's similar to the post object
 *
 * Class Wpil_Model_ExternalPost
 */
class Wpil_Model_ExternalPost
{
    public $id;
    public $title;
    public $stemmedTitle;
    public $type;
    public $post_url;
    public $site_url; // the url of the site the object comes from
    public $links;
    public $post_type;


    public function __construct($external_post_data = array())
    {
        $external_post_data = (object) $external_post_data;
        $data = array(
            'id' => 'post_id',
            'title' => 'post_title',
            'stemmedTitle' => 'stemmed_title',
            'type' => 'type',
            'post_url' => 'post_url',
            'site_url' => 'site_url',
            'post_type' => 'post_type',
        );

        //fill model properties from initial array
        foreach ($data as $key => $value) {
            if(isset($external_post_data->$value)){
                $this->{$key} = $external_post_data->$value;
            }
        }
    }

    /**
     * Gets all the data for this object from the database and sets all the indexes.
     * Requires that the post id, type, and origin site are set
     **/
    function getData(){

        if(!isset($this->id) || !isset($this->type) || !isset($this->site_url)){
            return false;
        }

        $item_data = Wpil_SiteConnector::get_data_item($this->id, $this->type, $this->site_url);

        if(!empty($item_data)){
            $item_data = $item_data[0]; // unwrap the data
            $data = array(
            'id' => $item_data->post_id,
            'title' => $item_data->post_title,
            'stemmedTitle' => $item_data->stemmed_title,
            'type' => $item_data->type,
            'post_url' => $item_data->post_url,
            'site_url' => $item_data->site_url,
            'post_type' => $item_data->post_type
            );

            foreach ($data as $key => $value) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Obtains the item title from the object data
     **/
    function getTitle()
    {
        if(empty($this->title)){
            $this->getData();
        }

        return $this->title;
    }

    function getLinks()
    {
        if(empty($this->links)){

            if(empty($this->post_url)){
                $this->getData();
            }

            $this->links = (object)[
                'view' => $this->post_url,
                'edit' => '',
                'export' => '',
                'excel_export' => '',
                'refresh' => '',
            ];
        }

        return $this->links;
    }

    /**
     * Get real post type
     *
     * @return string
     */
    function getRealType()
    {
        if(empty($this->post_type)){
            $this->getData();
        }

        return $this->post_type;
    }

    /**
     * Gets the real post type, but uppercases the type.
     **/
    function getType(){
        return ucfirst($this->getRealType());
    }

    /**
     * Get post status
     *
     * @return string
     */
    function getStatus()
    {
        return 'publish'; // The external data currently only contains published posts
    }

    /**
     * Misnomer for compatibility.
     * Since the getSlug method is only called on the table_suggesions page, it's simpler to misname a function than to create a bunch of if-elses to check object type
     * 
     * It returns the full post url, not just the post slug.
     **/
    function getSlug(){
        if(empty($this->post_url)){
            $this->getData();
        }

        return $this->post_url;
    }
}