<?php

include __DIR__ . '/mailer.php';
class SuperfastMailgunNewsletter extends NewsletterMailerAddon
{
    /* @var SuperfastMailgunNewsletter */
    static  $instance ;
    // Title display in Admin UI
    private  $title = 'Superfast Mailgun' ;
    function __construct( $version )
    {
        self::$instance = $this;
        parent::__construct( 'sfmailgun', $version );
        // Override textdomain loaded by superclass NewsletterAddon
        load_plugin_textdomain( 'sfmailgun', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        // Initialize options that should be enabled by default
        if ( !array_key_exists( 'verifyssl', $this->options ) ) {
            $this->options['verifyssl'] = true;
        }
    }
    
    function init()
    {
        global  $sfmailgun_fs ;
        parent::init();
        
        if ( !empty($this->options['enabled']) ) {
            add_filter( 'cron_schedules', array( $this, 'hook_cron_schedules' ), 20 );
            // ignored if priority is 10
            add_action(
                'newsletter_send_skip',
                array( $this, 'hook_newsletter_send_skip' ),
                10,
                2
            );
            add_action( SFMAILGUN_POLL_HOOK, array( $this, 'poll' ) );
        }
        
        
        if ( is_admin() ) {
            add_action( 'admin_menu', array( $this, 'hook_admin_menu' ), 100 );
            add_filter( 'newsletter_menu_settings', array( $this, 'hook_newsletter_menu_settings' ) );
            $sfmailgun_fs->add_filter( 'templates/account.php', array( $this, 'hook_freemius_page' ) );
            $sfmailgun_fs->add_filter( 'templates/checkout.php', array( $this, 'hook_freemius_page' ) );
            $sfmailgun_fs->add_filter( 'templates/connect.php', array( $this, 'hook_freemius_page' ) );
            $sfmailgun_fs->add_filter( 'templates/contact.php', array( $this, 'hook_freemius_page' ) );
            $sfmailgun_fs->add_filter( 'templates/pricing.php', array( $this, 'hook_freemius_page' ) );
            
            if ( !defined( 'DOING_CRON' ) || !DOING_CRON ) {
                $new_schedule = 'sfmailgunpoll';
                $current_schedule = wp_get_schedule( SFMAILGUN_POLL_HOOK );
                
                if ( $current_schedule === false ) {
                    wp_schedule_event( time() + 60, $new_schedule, SFMAILGUN_POLL_HOOK );
                } elseif ( $current_schedule != $new_schedule ) {
                    wp_clear_scheduled_hook( SFMAILGUN_POLL_HOOK );
                    wp_schedule_event( time() + 60, $new_schedule, SFMAILGUN_POLL_HOOK );
                }
            
            }
        
        }
    
    }
    
    function hook_cron_schedules( $schedules )
    {
        // add a schedule to the existing set
        $schedules['sfmailgunpoll'] = array(
            'interval' => 60 * 30,
            'display'  => __( 'Every 30 minutes', 'sfmailgun' ),
        );
        return $schedules;
    }
    
    function hook_newsletter_menu_settings( $entries )
    {
        $entries[] = array(
            'label'       => "<i class='fas fa-fighter-jet'></i> {$this->title}",
            'url'         => '?page=newsletter_sfmailgun_index',
            'description' => 'Send emails with Mailgun fast',
        );
        return $entries;
    }
    
    function hook_admin_menu()
    {
        add_submenu_page(
            'newsletter_main_index',
            $this->title,
            "<span class='tnp-side-menu'>{$this->title}</span>",
            'manage_options',
            'newsletter_sfmailgun_index',
            array( $this, 'menu_page_index' )
        );
    }
    
    function menu_page_index()
    {
        require dirname( __FILE__ ) . '/index.php';
    }
    
    // Add header and footer to Freemius admin pages
    function hook_freemius_page( $page )
    {
        // Header
        ob_start();
        @(include NEWSLETTER_DIR . '/tnp-header.php');
        ?>
        <div id="tnp-heading">
        	<h2><?php 
        echo  $this->title ;
        ?></h2>
        </div>
        <style>.fs-secure-notice {position:static !important;}</style>
        <div style="background-color:rgb(241,241,241);">
        <?php 
        $header = ob_get_clean();
        // Footer
        ob_start();
        ?></div><?php 
        @(include NEWSLETTER_DIR . '/tnp-footer.php');
        $footer = ob_get_clean();
        return $header . $page . $footer;
    }
    
    /****************************** mailer registered with TNP **************************************/
    function get_mailer()
    {
        static  $mailer = null ;
        if ( !$mailer ) {
            $mailer = new SuperfastMailgunMailer( $this->name, $this->options );
        }
        return $mailer;
    }
    
    /**************************************** NEWSLETTER SEND ****************************************/
    /*
     * Send a newsletter
     * @param unknown $dummy  Not used
     * @param unknown $email  The email structure. See table newsletter_emails.
     */
    function hook_newsletter_send_skip( $dummy, $email )
    {
        $newsletter = Newsletter::instance();
        $logger = $this->get_logger();
        $logger->debug( 'hook_newsletter_send_skip() invoked.' );
        // Safeguard, same as Newsletter->send()
        if ( empty($email->query) ) {
            $email->query = "select * from " . NEWSLETTER_USERS_TABLE . " where status='C'";
        }
        $msubject = $email->subject;
        $mbody = $email->message;
        $mbody_text = $email->message_text;
        // Newsletter::build_message() also does this, *before* replace()
        //$mbody = preg_replace('/data-json=".*?"/is', '', $mbody);
        //$mbody = preg_replace('/  +/s', ' ', $mbody);
        // Tag translation: https://www.thenewsletterplugin.com/documentation/newsletter-tags
        // First replace all user-specific tags with their Mailgun equivalents and build the dictionary
        $tag_list = array();
        $msubject = $this->replace_tags( $msubject, $email, $tag_list );
        $mbody = $this->replace_tags( $mbody, $email, $tag_list );
        $mbody_text = $this->replace_tags( $mbody_text, $email, $tag_list );
        // Then let TNP replace the generic tags and apply the same filters as TNP (see Newsletter::build_message()),
        // except that we do not pass a specific user
        $msubject = $newsletter->replace( $msubject, null );
        // Note: TNP does not pass the email either in this case
        $msubject = apply_filters(
            'newsletter_message_subject',
            $msubject,
            $email,
            null
        );
        $mbody = $newsletter->replace( $mbody, null, $email );
        if ( $newsletter->options['do_shortcodes'] ) {
            // Shortcode option is under General Settings / Advanced
            $mbody = do_shortcode( $mbody );
        }
        $mbody = apply_filters(
            'newsletter_message_html',
            $mbody,
            $email,
            null
        );
        $mbody_text = $newsletter->replace( $mbody_text, null, $email );
        $mbody_text = apply_filters(
            'newsletter_message_text',
            $mbody_text,
            $email,
            null
        );
        // Prepare headers
        $headers = array();
        $headers['Precedence'] = 'bulk';
        $headers['X-Auto-Response-Suppress'] = 'OOF, AutoReply';
        $headers['X-Newsletter-Email-Id'] = $email->id;
        $headers['X-Mailgun-Track-Opens'] = ( $email->track ? 'yes' : 'no' );
        $headers['X-Mailgun-Track-Clicks'] = ( $email->track ? 'htmlonly' : 'no' );
        // Could make yes|htmlonly configurable
        if ( empty($newsletter->options['disable_unsubscribe_headers']) ) {
            $headers = $this->add_unsubscribe_headers( $headers, $email, $tag_list );
        }
        // Build the message
        $mailer_message = new TNP_Mailer_Message();
        $mailer_message->email_id = $email->id;
        $mailer_message->from = $newsletter->options['sender_email'];
        $mailer_message->from_name = $newsletter->options['sender_name'];
        $mailer_message->headers = $headers;
        $mailer_message->subject = $msubject;
        $mailer_message->body = $mbody;
        $mailer_message->body_text = $mbody_text;
        $mailer_message = apply_filters(
            'newsletter_message',
            $mailer_message,
            $email,
            null
        );
        // Number of emails sent per invocation
        $max_total = $this->get_mailer()->get_batch_size();
        $max_emails = $max_total;
        $query = $email->query;
        $query .= " and id>{$email->last_id} order by id limit {$max_emails}";
        $logger->debug( "query={$query}" );
        $users = $this->get_results( $query );
        
        if ( !empty($users) ) {
            $ok = $this->batch_send(
                $email,
                $mailer_message,
                $tag_list,
                $users
            );
            
            if ( !$ok ) {
                return true;
                // If we return false, TNP sends one by one
            }
            
            $email->last_id = end( $users )->id;
            $total_sent = count( $users );
            $this->query( "update " . NEWSLETTER_EMAILS_TABLE . " set sent=sent+{$total_sent}, last_id={$email->last_id} where id={$email->id} limit 1" );
        } else {
            // Mark the email as sent.
            $logger->info( 'send> No more users, set as sent' );
            $this->query( "update " . NEWSLETTER_EMAILS_TABLE . " set status='sent', total=sent where id={$email->id} limit 1" );
        }
        
        // Tell Newsletter plugin that we took care of the sending
        return true;
    }
    
    // Send to all given users using Mailgun batch send
    // Returns true on success, false on failure
    private function batch_send(
        $email,
        $mailer_message,
        $tag_list,
        $users
    )
    {
        $newsletter = Newsletter::instance();
        $logger = $this->get_logger();
        $startsend = microtime( true );
        // Get a PHPMailer so we can use its address formatting function
        $logger->debug( 'WP version is ' . get_bloginfo( 'version' ) );
        
        if ( version_compare( get_bloginfo( 'version' ), '5.5', '<' ) ) {
            if ( !class_exists( '\\PHPMailer', false ) ) {
                require_once ABSPATH . WPINC . '/class-phpmailer.php';
            }
            $phpmailer = new PHPMailer();
        } else {
            if ( !class_exists( '\\PHPMailer\\PHPMailer\\PHPMailer', false ) ) {
                require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            }
            $phpmailer = new \PHPMailer\PHPMailer\PHPMailer();
        }
        
        $logger->debug( 'phpmailer class is ' . get_class( $phpmailer ) );
        $phpmailer->CharSet = 'UTF-8';
        // Build the to list for this batch
        $mailer_message->to = array();
        foreach ( $users as $u ) {
            // Build the list of recipients:  Display Name <someone@example.com>
            $addr = array( $u->email, trim( $u->name . ' ' . $u->surname ) );
            $mailer_message->to[] = $phpmailer->addrFormat( $addr );
        }
        // Build the recipient-variables json structure
        $recipvars_json = '';
        
        if ( !empty($tag_list) ) {
            foreach ( $users as $u ) {
                $tagdict = $this->build_tag_dict( $tag_list, $u );
                $tagdict_json = json_encode( $tagdict );
                $recipvars_json .= ",\"{$u->email}\":{$tagdict_json}";
            }
            // Remove the leading comma
            $recipvars_json = substr( $recipvars_json, 1 );
        }
        
        $mailer_message->recipient_variables = '{' . $recipvars_json . '}';
        // This field is ours only
        // Send a batch. Get the Newsletter mailer, for compatibility with plaintext-newsletter.
        $retval = $newsletter->get_mailer()->send( $mailer_message );
        
        if ( $retval !== true ) {
            $logger->error( 'Batch send failed:' );
            $logger->error( $retval );
            return false;
        }
        
        // Record successful deliveries
        $deltatime = microtime( true );
        foreach ( $users as $u ) {
            $this->save_sent( $u->id, $email->id );
        }
        $deltatime = microtime( true ) - $deltatime;
        $logger->debug( "Calling save_sent() took {$deltatime} seconds" );
        // Log elapsed time
        $deltatime = microtime( true ) - $startsend;
        $logger->info( "Successfully sent email {$email->id} to " . count( $users ) . " subscribers in {$deltatime} seconds" );
        return true;
    }
    
    // Adds the List-Unsubscribe headers
    // Similar to NewsletterUnsubscription::hook_add_unsubscribe_headers_to_email()
    function add_unsubscribe_headers( $headers, $email, &$tag_list )
    {
        $unsubscription = NewsletterUnsubscription::instance();
        if ( isset( $unsubscription->options['disable_unsubscribe_headers'] ) && $unsubscription->options['disable_unsubscribe_headers'] == 1 ) {
            return $headers;
        }
        $list_unsubscribe_values = [];
        
        if ( !empty($unsubscription->options['list_unsubscribe_mailto_header']) ) {
            $unsubscribe_address = $unsubscription->options['list_unsubscribe_mailto_header'];
            $list_unsubscribe_values[] = "<mailto:{$unsubscribe_address}?subject=unsubscribe>";
        }
        
        $list_unsubscribe_values[] = $this->replace_tags( '<{list_unsubscribe_url}>', $email, $tag_list );
        $headers['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
        $headers['List-Unsubscribe'] = implode( ', ', $list_unsubscribe_values );
        //$this->get_logger()->debug('List-Unsubscribe header=' . print_r($headers['List-Unsubscribe'], true));
        return $headers;
    }
    
    // Returns the text with TNP tags replaced with mailgun tags and updates the list of tags used in this email
    // See https://www.thenewsletterplugin.com/documentation/newsletter-tags
    function replace_tags( $text, $email, &$tag_list )
    {
        // The tags are the keys in $tag_list to ensure they are included only once; the value associated with each key is irrelevant.
        // SUBSCRIBER SPECIFIC TAGS
        // Note: TNP also supports some undocumented tags, e.g. last_name=surname and full_name=name, but unfiltered.
        // {id} the subscriber's unique ID
        
        if ( strpos( $text, '{id}' ) !== false ) {
            $tag_list['id'] = 1;
            $text = str_replace( '{id}', '%recipient.id%', $text );
        }
        
        // {name} the subscriber's name or first name, it depends on how you use that fields during subscription
        
        if ( strpos( $text, '{name}' ) !== false ) {
            $tag_list['name'] = 1;
            $text = str_replace( '{name}', '%recipient.name%', $text );
        }
        
        // {surname} the subscriber's last name
        
        if ( strpos( $text, '{surname}' ) !== false ) {
            $tag_list['surname'] = 1;
            $text = str_replace( '{surname}', '%recipient.surname%', $text );
        }
        
        // {title} the subscriber's title or salutation, like Mr or Mrs, can be configured on the subscription panel
        
        if ( strpos( $text, '{title}' ) !== false ) {
            $tag_list['title'] = 1;
            $text = str_replace( '{title}', '%recipient.title%', $text );
        }
        
        // {email} the subscriber's email
        if ( strpos( $text, '{email}' ) !== false ) {
            // Supported by Mailgun
            $text = str_replace( '{email}', '%recipient_email%', $text );
        }
        // {profile_N} the profile number N as configured on subscription form fields
        // Note: TNP has a bug: it only loops from 1 to 19
        for ( $i = 1 ;  $i <= NEWSLETTER_PROFILE_MAX ;  $i++ ) {
            $pname = 'profile_' . $i;
            $ptag = '{' . $pname . '}';
            
            if ( strpos( $text, $ptag ) !== false ) {
                $tag_list[$pname] = 1;
                $text = str_replace( $ptag, "%recipient.{$pname}%", $text );
            }
        
        }
        // {ip} Not Supported, mainly useful on confirmation email
        // {key} userid-token (undocumented), not directly supported but used in URLs below
        // SFMAILGUN EXTRAS
        // If the name field has both first and last name, these tags allow you to use both parts separately
        if ( strpos( $text, '{firstname}' ) !== false ) {
            // Supported by Mailgun
            $text = str_replace( '{firstname}', '%recipient_fname%', $text );
        }
        if ( strpos( $text, '{lastname}' ) !== false ) {
            // Supported by Mailgun
            $text = str_replace( '{lastname}', '%recipient_lname%', $text );
        }
        // SUBSCRIPTION/UNSUBSCRIPTION/PROFILE PAGE PROCESS TAGS
        // See NewsletterModule::build_action_url()
        // {subscription_confirm_url} Not supported - to be used only on confirmation email when the double opt-in is used
        // {unsubscription_url} to drive the user to the unsubscription page where he's asked to confirm he want to unsubscribe
        
        if ( strpos( $text, '{unsubscription_url}' ) !== false ) {
            $tag_list['key'] = 1;
            $url = home_url( '/' ) . "?na=u&nk=%recipient.key%&nek={$email->id}-{$email->token}";
            $text = str_replace( '{unsubscription_url}', $url, $text );
        }
        
        // {unsubscription_confirm_url} to definitively unsubscribe; can be used on every email for the "one click unsubscription"
        
        if ( strpos( $text, '{unsubscription_confirm_url}' ) !== false ) {
            $tag_list['key'] = 1;
            $url = home_url( '/' ) . "?na=uc&nk=%recipient.key%&nek={$email->id}-{$email->token}";
            $text = str_replace( '{unsubscription_confirm_url}', $url, $text );
        }
        
        // {list_unsubscribe_url} is used in the List-Unsubscribe header
        
        if ( strpos( $text, '{list_unsubscribe_url}' ) !== false ) {
            $tag_list['key'] = 1;
            $url = home_url( '/' ) . "?na=lu&nk=%recipient.key%&nek={$email->id}-{$email->token}";
            $text = str_replace( '{list_unsubscribe_url}', $url, $text );
        }
        
        // {profile_url} point directly to the profile editing page;
        
        if ( strpos( $text, '{profile_url}' ) !== false ) {
            $tag_list['key'] = 1;
            $url = home_url( '/' ) . "?na=profile&nk=%recipient.key%";
            $text = str_replace( '{profile_url}', $url, $text );
        }
        
        // OTHER TAGS
        // {email_subject} is used by template emails
        
        if ( strpos( $text, '{email_subject}' ) !== false ) {
            // Recursive call
            $msubject = $this->replace_tags( $email->subject, $email, $tag_list );
            $text = str_replace( '{email_subject}', $msubject, $text );
        }
        
        return $text;
    }
    
    // Builds a tag dictionary for the given user
    function build_tag_dict( $tag_list, $user )
    {
        $newsletter = Newsletter::instance();
        $vars = array();
        foreach ( array_keys( $tag_list ) as $t ) {
            switch ( $t ) {
                case 'id':
                    $vars['id'] = $user->id;
                    break;
                case 'name':
                    $name = apply_filters( 'newsletter_replace_name', $user->name, $user );
                    $vars['name'] = $name;
                    break;
                case 'surname':
                    $vars['surname'] = $user->surname;
                    break;
                case 'title':
                    $options_profile = NewsletterSubscription::instance()->get_options( 'profile', $newsletter->get_user_language( $user ) );
                    switch ( $user->sex ) {
                        case 'm':
                            $vars['title'] = $options_profile['title_male'];
                            break;
                        case 'f':
                            $vars['title'] = $options_profile['title_female'];
                            break;
                        case 'n':
                            $vars['title'] = $options_profile['title_none'];
                            break;
                        default:
                            $vars['title'] = '';
                    }
                    break;
                case 'key':
                    $vars['key'] = "{$user->id}-{$user->token}";
                    break;
                default:
                    for ( $i = 1 ;  $i <= NEWSLETTER_PROFILE_MAX ;  $i++ ) {
                        $pname = 'profile_' . $i;
                        if ( $t == $pname ) {
                            $vars[$pname] = $user->{$pname};
                        }
                    }
            }
        }
        //$this->get_logger()->debug("recipvars for {$user->email}=". print_r($vars, true));
        return $vars;
    }
    
    /**************************************** EVENT POLLING ****************************************/
    var  $poll_result ;
    // used by index.php in Bounces tab
    function poll()
    {
        $newsletter = Newsletter::instance();
        $stats = NewsletterStatistics::instance();
        $logger = $this->get_logger();
        // The most recent events returned by the events API may not contain all the events that occurred by that time.
        // See https://documentation.mailgun.com/api-events.html#event-polling
        // So avoid querying events that occurred in the last 30 minutes (this duration was recommended by Mailgun support)
        $starttime = $this->get_last_run();
        $endtime = time() - 30 * 60;
        // Event types: https://documentation.mailgun.com/en/latest/api-events.html#event-types
        $eventlist = array(
            'rejected',
            'complained',
            'failed',
            'unsubscribed'
        );
        $encoded_eventlist = '(' . urlencode( implode( ' OR ', $eventlist ) ) . ')';
        $url = 'https://api:' . $this->options['api_key'] . '@' . $this->get_mailer()->get_api_domain() . '/events?event=' . $encoded_eventlist . '&begin=' . $starttime . '&end=' . $endtime . '&limit=300&severity=' . urlencode( '(NOT temporary)' );
        // Note: For security reasons, we don't log the API key.
        //$logger->debug('url=' . $url); // TEMP DEBUG
        $logger->info( 'poll() BEGIN=' . $this->formatted_date( $starttime ) . ' END=' . $this->formatted_date( $endtime ) );
        $response = wp_remote_get( $url );
        
        if ( is_wp_error( $response ) ) {
            /* @var $response WP_Error */
            $this->poll_result = $response->get_error_message();
            $logger->error( $response );
            return false;
        }
        
        $http_code = wp_remote_retrieve_response_code( $response );
        
        if ( $http_code != 200 ) {
            $this->poll_result = 'HTTP Error ' . $http_code . '<br>' . wp_remote_retrieve_body( $response );
            $logger->error( 'HTTP Error ' . $http_code );
            return false;
        }
        
        $data = json_decode( wp_remote_retrieve_body( $response ) );
        $logger->info( 'Event count=' . count( $data->items ) );
        // Start time stays unchanged if there are no events
        $next_starttime = $starttime;
        $count = 0;
        $bounce_count = 0;
        foreach ( $data->items as $event ) {
            // Event structure: https://documentation.mailgun.com/en/latest/api-events.html#event-structure
            $count++;
            $logger->info( 'Event received #' . $count . ': ' . $event->event . ' ' . $event->recipient . ' at: ' . $this->formatted_date( $event->timestamp ) );
            // Next start time is the time of the last event, so that the next run starts from there
            // Add a millisecond to avoid getting the last event again
            $next_starttime = $event->timestamp + 0.001;
            $user_email = strtolower( $event->recipient );
            
            if ( is_null( $user_email ) ) {
                // "Rejected" event has no recipient field ???
                $logger->error( "Event does not have a recipient" );
                $user_email = $event->message->headers->to;
                // The "to" field may contain the name, e.g. "Joe <joseph@example.com>"
                $regex_matches = array();
                $regex_rc = preg_match( '.*<(.*)>', $user_email, $regex_matches );
                if ( $regex_rc == 1 && count( $regex_matches ) == 2 ) {
                    $user_email = $regex_matches[1];
                }
                
                if ( is_null( $user_email ) ) {
                    // Unable to find recipient. Skip this event.
                    $logger->error( "Event recipient not found in 'to' field: {$event->message->headers->to}" );
                    continue;
                }
            
            }
            
            $user = $newsletter->get_user( $user_email );
            
            if ( !$user ) {
                // Recipient not found. Skip this event.
                $logger->error( "Event recipient {$user_email} not found" );
                continue;
            }
            
            $user_id = $user->id;
            $tag = $event->tags[0];
            
            if ( is_null( $tag ) ) {
                // Not all events have a tag
                $email_id = 0;
            } else {
                // Example: "tags": [ "Newsletter 46" ]
                $email_id = intval( str_replace( 'Newsletter ', '', $tag ) );
            }
            
            
            if ( 'unsubscribed' == $event->event ) {
                // Mark as unsubscribed in the Newsletter database
                $user = $newsletter->set_user_status( $user, TNP_User::STATUS_UNSUBSCRIBED );
                $newsletter->add_user_log( $user, 'mailgun-unsubscribed' );
                $curtime = time();
                $this->query( "update " . NEWSLETTER_USERS_TABLE . " set unsub_email_id={$email_id}, unsub_time={$curtime} where id={$user->id} limit 1" );
            } elseif ( 'failed' == $event->event ) {
                
                if ( 'suppress-unsubscribe' == $event->reason ) {
                    $logger->info( '   Suppressed: ' . $event->message->headers->to . ' ' . $event->reason );
                    // Make sure the user is marked as unsubscribed
                    $user = $newsletter->set_user_status( $user, TNP_User::STATUS_UNSUBSCRIBED );
                    $newsletter->add_user_log( $user, 'suppress-unsubscribe' );
                } else {
                    $logger->debug( '   Bounce: ' . $event->message->headers->to . ' ' . $event->reason );
                    $bounce_count++;
                    // Record error in the newsletter_sent table
                    if ( $email_id != 0 ) {
                        $this->save_sent( $user_id, $email_id, $event->reason );
                    }
                    // Mark as bounced in the Newsletter database
                    $user = $newsletter->set_user_status( $user, TNP_User::STATUS_BOUNCED );
                    $newsletter->add_user_log( $user, 'failed' );
                }
            
            } elseif ( 'rejected' == $event->event ) {
                $logger->debug( '   Bounce: ' . $event->message->headers->to . ' rejected' );
                $bounce_count++;
                // Record error in the newsletter_sent table
                if ( $email_id != 0 ) {
                    $this->save_sent( $user_id, $email_id, $event->reject->reason );
                }
                // Mark as bounced in the Newsletter database
                $user = $newsletter->set_user_status( $user, TNP_User::STATUS_BOUNCED );
                $newsletter->add_user_log( $user, 'rejected' );
            } elseif ( 'complained' == $event->event ) {
                $logger->debug( '   Bounce: ' . $event->message->headers->to . ' complained' );
                $bounce_count++;
                // Record error in the newsletter_sent table
                if ( $email_id != 0 ) {
                    $this->save_sent( $user_id, $email_id, 'complained' );
                }
                // Mark as bounced in the Newsletter database
                $user = $newsletter->set_user_status( $user, TNP_User::STATUS_BOUNCED );
                $newsletter->add_user_log( $user, 'complained' );
            } else {
                $logger->debug( '   Unhandled event.' );
            }
        
        }
        $logger->debug( "Saving next_starttime= {$next_starttime} " . $this->formatted_date( $next_starttime ) );
        $this->save_last_run( $next_starttime );
        return $bounce_count;
    }
    
    /**************************************** UTILITIES ****************************************/
    /**
     * Format a microseconds timestamp (truncating the microseconds)
     */
    function formatted_date( $timestamp )
    {
        $dt = new DateTime();
        $dt->setTimestamp( (int) $timestamp );
        $fdt = $dt->format( 'Y-m-d H:i:s O' );
        return $fdt;
    }
    
    /**
     * Similar to NewsletterAddon::query(): checks for error
     * @global wpdb $wpdb
     * @param string $query
     */
    function get_results( $query )
    {
        global  $wpdb ;
        $r = $wpdb->get_results( $query );
        
        if ( !empty($wpdb->last_error) ) {
            $logger = $this->get_logger();
            $logger->fatal( $query );
            $logger->fatal( $wpdb->last_error );
        }
        
        return $r;
    }
    
    // Wrapper for Newsletter save_sent_message() function
    function save_sent( $user_id, $email_id, $error = '' )
    {
        //$logger = $this->get_logger();
        //$logger->debug("save_sent $user_id $email_id $error");
        // Only fill in the fields that save_sent_message() uses
        $newsletter = Newsletter::instance();
        $message = (object) [
            'user_id'  => $user_id,
            'email_id' => $email_id,
            'error'    => $error,
        ];
        $newsletter->save_sent_message( $message );
    }
    
    // This is a clone of NewsletterStatistics::add_click(), modified to set the timestamp to the one received from Mailgun
    // and to avoid duplicate entries
    // Note: $ip is assumed to have been processed by Newsletter::process_ip()
    function add_click(
        $url,
        $user_id,
        $email_id,
        $ip,
        $timestamp
    )
    {
        // Insert each event only once
        $stats = NewsletterStatistics::instance();
        $dbrc = $stats->query( "SELECT id FROM " . NEWSLETTER_STATS_TABLE . " where user_id={$user_id} and email_id={$email_id} and url='{$url}'" );
        if ( $dbrc ) {
            return;
        }
        // Get timezone configured in WP General Settings
        // See https://wordpress.stackexchange.com/questions/8400/how-to-get-wordpress-time-zone-setting
        $timezone = get_option( 'timezone_string' );
        
        if ( empty($timezone) ) {
            $offset = get_option( 'gmt_offset' );
            $hours = (int) $offset;
            $minutes = abs( ($offset - (int) $offset) * 60 );
            $timezone = sprintf( '%+03d:%02d', $hours, $minutes );
        }
        
        // Convert Unix timestamp to local time
        $dt = new DateTime();
        $dt->setTimestamp( (int) $timestamp );
        $dt->setTimezone( new DateTimeZone( $timezone ) );
        $created = $dt->format( 'Y-m-d H:i:s' );
        $stats->insert( NEWSLETTER_STATS_TABLE, array(
            'email_id' => $email_id,
            'user_id'  => $user_id,
            'created'  => $created,
            'url'      => $url,
            'ip'       => $ip,
        ) );
    }

}