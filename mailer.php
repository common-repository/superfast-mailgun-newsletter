<?php

class SuperfastMailgunMailer extends NewsletterMailer
{
    function __construct( $name, $options = array() )
    {
        parent::__construct( $name, $options );
        $this->batch_size = NEWSLETTER_CRON_INTERVAL / 3;
    }
    
    function get_description()
    {
        return 'Superfast Mailgun add-on';
    }
    
    /* Mailers can return their speed (emails sent per hour), overriding the Delivery Speed set in Newsletter Settings
     * (since Newsletter 7.3.1)
     */
    public function get_speed()
    {
        return $this->batch_size * 3600 / NEWSLETTER_CRON_INTERVAL;
    }
    
    function get_api_base()
    {
        
        if ( !empty($this->options['api_region']) && $this->options['api_region'] == 'eu' ) {
            $domain = 'api.eu.mailgun.net/v3/';
        } else {
            $domain = 'api.mailgun.net/v3/';
        }
        
        return $domain;
    }
    
    function get_api_domain()
    {
        $domain = $this->get_api_base() . $this->options['api_domain'];
        return $domain;
    }
    
    function get_api_url()
    {
        static  $url = null ;
        if ( $url ) {
            return $url;
        }
        $logger = $this->get_logger();
        $logger->debug( 'Region ' . $this->options['api_region'] );
        $url = 'https://' . $this->get_api_domain();
        $logger->debug( 'API URL: ' . $url );
        return $url;
    }
    
    /**
     *
     * @param TNP_Mailer_Message $message
     * @return array
     */
    function build_data( $message )
    {
        $logger = $this->get_logger();
        $newsletter = Newsletter::instance();
        $data = array();
        //$data['o:testmode'] = true; // Make this configurable???
        
        if ( is_array( $message->to ) ) {
            $logger->info( 'Sending message to ' . count( $message->to ) . ' subscribers' );
            // $message->to includes name and email address
            $data['to'] = implode( ',', $message->to );
            //$logger->debug('to length=' . strlen($data['to']));
        } else {
            $logger->info( "Sending message to {$message->to}" );
            // to_name not populated by TNP currently
            $data['to'] = $message->to;
        }
        
        if ( !empty($message->body) ) {
            $data['html'] = $message->body;
        }
        if ( !empty($message->body_text) ) {
            $data['text'] = $message->body_text;
        }
        $data['subject'] = $message->subject;
        // Note: class TNP_Mailer_Message does not initialize/declare these properties, but they are populated
        $data['from'] = "{$message->from_name} <{$message->from}>";
        if ( !empty($newsletter->options['reply_to']) ) {
            $data['h:Reply-To'] = $newsletter->options['reply_to'];
        }
        foreach ( $message->headers as $h => $v ) {
            
            if ( $h == 'X-Newsletter-Email-Id' ) {
                $data['o:tag'] = "Newsletter {$v}";
            } elseif ( $h == 'X-Mailgun-Track-Opens' ) {
                $data['o:tracking-opens'] = $v;
            } elseif ( $h == 'X-Mailgun-Track-Clicks' ) {
                $data['o:tracking-clicks'] = $v;
            } else {
                $logger->debug( "Header {$h}:{$v}" );
                $data["h:{$h}"] = $v;
            }
        
        }
        if ( !empty($message->recipient_variables) ) {
            //$logger->debug('recipvars length=' . strlen($message->recipient_variables));
            $data['recipient-variables'] = $message->recipient_variables;
        }
        /*
        $temp_data = $data;
        $temp_data['to'] = '{' . strlen($data['to']) . ' bytes}';
        $temp_data['recipient-variables'] = '{' . strlen($data['recipient-variables']) . ' bytes}';
        $temp_data['html'] = '{html}';
        $temp_data['text'] = '{text}';
        $logger->debug('request data=' . print_r($temp_data, true));
        */
        return $data;
    }
    
    /**
     * NOTE: Our implementation of send() supports an array of addresses in $message->to
     * 
     * @param TNP_Mailer_Message $message
     * @return \WP_Error|boolean
     */
    function send( $message )
    {
        $logger = $this->get_logger();
        $url = $this->get_api_url() . '/messages';
        $args = array(
            'headers'   => array(
            'Authorization' => 'Basic ' . base64_encode( 'api:' . $this->options['api_key'] ),
        ),
            'body'      => $this->build_data( $message ),
            'sslverify' => $this->options['verifyssl'],
            'timeout'   => 60,
        );
        $response = wp_remote_post( $url, $args );
        //$logger->debug("Http response: " . print_r($response, true));
        
        if ( is_wp_error( $response ) ) {
            $errmsg = $response->get_error_code() . ' - ' . $response->get_error_message();
            $logger->error( $errmsg );
            $message->error = $errmsg;
            // Used by TNP to log error but $message is not passed by reference
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        //$logger->debug("Http response code: $code");
        
        if ( $code != 200 ) {
            $errbody = wp_remote_retrieve_body( $response );
            $logger->error( "Http error {$code} " . print_r( $errbody, true ) );
            $message->error = "Http error {$code}";
            // Used by TNP to log error but not relevant because we run in 'newsletter_send_skip' filter
            return new WP_Error( self::ERROR_GENERIC, "Http error {$code}", $errbody );
        }
        
        return true;
    }

}