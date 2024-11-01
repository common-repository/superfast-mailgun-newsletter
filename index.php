<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
/* @var $this SuperfastMailgunNewsletter */
require_once NEWSLETTER_INCLUDES_DIR . '/controls.php';
$controls = new NewsletterControls();

if ( !$controls->is_action() ) {
    $controls->data = $this->options;
} else {
    
    if ( $controls->is_action( 'save' ) ) {
        $this->save_options( $controls->data );
        $controls->messages = 'Saved.';
    }
    
    
    if ( $controls->is_action( 'trigger' ) ) {
        $res = $this->poll();
        
        if ( !empty($this->poll_result) ) {
            $controls->errors = $this->poll_result;
        } else {
            $controls->messages = 'Done. Found ' . $res . ' events.';
        }
    
    }
    
    
    if ( $controls->is_action( 'reset' ) ) {
        $this->save_last_run( 0 );
        $controls->messages = 'Done.';
    }
    
    
    if ( $controls->is_action( 'test' ) ) {
        $message = $this->get_test_message( $controls->data['test_email'] );
        
        if ( $controls->data['test_domain'] != 'newsletter' ) {
            // Make it a transactional message
            unset( $message->headers['X-Newsletter-Email-Id'] );
            $message->subject .= ' Transactional';
        }
        
        $result = $this->get_mailer()->send( $message );
        
        if ( is_wp_error( $result ) ) {
            $controls->errors .= 'Delivery error: ' . $result->get_error_message() . '<br>';
        } else {
            $controls->messages = 'Test message successfully sent.';
        }
    
    }

}

if ( empty($controls->data['enabled']) ) {
    $controls->warnings[] = 'The extension is not enabled. After you configured and tested it, remember to enable it.';
}
$current_mailer = Newsletter::instance()->get_mailer();
if ( !empty($controls->data['enabled']) && !is_a( $current_mailer, 'SuperfastMailgunMailer' ) ) {
    $controls->warnings[] = 'There is another integration active: ' . $current_mailer->get_description();
}
if ( class_exists( 'NewsletterBounce' ) ) {
    $controls->warnings[] = 'The Bounce Addon is active, but bounces are managed by this addon: you can save some resources by deactivating the Bounce Addon.';
}
global  $sfmailgun_fs ;
?>

<style>
.freemius-links {
    position:relative;
    left:265px;
    margin-bottom:-25px
}
.freemius-links p, .freemius-links p a {
    color:white !important;
    margin:0;
}
</style>
<div class="wrap" id="tnp-wrap">
    <?php 
@(include NEWSLETTER_DIR . '/tnp-header.php');
?>
    <div id="tnp-heading">
        <h2><?php 
echo  $this->title ;
?></h2>
        <?php 
$controls->show();
?>
    </div>
    <div id="tnp-body">
        <form action="" method="post">
            <?php 
$controls->init();
?>

            <div id="tabs">
    			<div class="freemius-links">
    				<p>
    				<?php 

if ( $sfmailgun_fs->is_paying() ) {
    ?>
    				&#x279C;<a href="<?php 
    echo  $sfmailgun_fs->get_account_url() ;
    ?>">Account</a>
    				&nbsp;&nbsp;&nbsp;&nbsp;
    				<?php 
}

?>
    				&#x279C;<a href="<?php 
echo  $sfmailgun_fs->contact_url() ;
?>">Contact Us</a>
    				&nbsp;&nbsp;&nbsp;&nbsp;
    				&#x279C;<a href="<?php 
echo  $sfmailgun_fs->get_support_forum_url() ;
?>" target="_blank">Support Forum</a>
    				<?php 
$upgrade_url = $sfmailgun_fs->get_upgrade_url();
?>
    				&nbsp;&nbsp;&nbsp;&nbsp;
    				&#x279C;<a href="<?php 
echo  $upgrade_url ;
?>">Upgrade</a>
    				<?php 
?>
    				</p>
    			</div>

                <ul>
                    <li><a href="#tabs-general">General</a></li>
                    <li><a href="#tabs-advanced">Advanced</a></li>
                    <li><a href="#tabs-polling">Polling</a></li>
                </ul>

                <div id="tabs-general">
                    <table class="form-table">
                        <tr valign="top">
                            <th>Enabled?</th>
                            <td>
                                <?php 
$controls->enabled( 'enabled' );
?>
                                <p class="description">You can do tests without enabling it</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th>API Domain</th>
                            <td>
                                <?php 
$controls->text( 'api_domain', 50 );
?>
                                <p class="description">
                                    The domain you configured in Mailgun to send newsletters to your subscribers.
                                </p>
                            </td>
                        </tr>    
                        <tr valign="top">
                            <th>API Region</th>
                            <td>
                                <?php 
$controls->select( 'api_region', array(
    'us' => 'US',
    'eu' => 'EU',
) );
?>
                                <p class="description"></p>
                            </td>
                        </tr>   
                        <tr valign="top">
                            <th>API Key</th>
                            <td>
                                <?php 
$controls->text( 'api_key', 50 );
?>
                                <p class="description"></p>
                            </td>
                        </tr>
                        <tr>
                            <th>To test this configuration</th>
                            <td>
                                <?php 
$controls->select( 'test_domain', array(
    'newsletter'    => 'Send a newsletter message',
    'transactional' => 'Send a transactional message',
) );
?>
                                &nbsp;to&nbsp;
                                <?php 
$controls->text( 'test_email', 30 );
?>
                                <?php 
$controls->button( 'test', 'Send a message to this email' );
?>
                            </td>
                        </tr>
                    </table>
                </div>

                <div id="tabs-advanced">
                    <table class="form-table">
                        <tr valign="top">
                            <th>Max emails per hour</th>
                            <td>
                                <?php 
?>
                                <select disabled="disabled">
                                	<option>1,200</option>
                                </select>
								<?php 
?>
                            	<p class="description">
                            		Maximum number of newsletter emails sent per hour. This overrides the main Newsletter Delivery Speed setting.
                            		<?php 
?>
                            		<a href="<?php 
echo  $upgrade_url ;
?>">Upgrade to Pro</a> to send up to 120,000 emails per hour.
                            		<?php 
?>
                            	</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th>Use same route for transactional messages</th>
                            <td>
                            	<?php 
?>
                                <select disabled="disabled">
                                	<option>Yes</option>
                                </select>
								<?php 
?>
                                <p class="description">
                                    Also use Mailgun with the configured domain for transactional messages to individual subscribers (e.g. Welcome message).<br />
                               		<?php 
?>
                                    <a href="<?php 
echo  $upgrade_url ;
?>">Upgrade to Pro</a> to be able to use a different route for transactional messages.
                                    <?php 
?>
                                </p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th>Verify SSL certificate</th>
                            <td>
                            	<?php 
$controls->yesno( 'verifyssl' );
?>
                                <p class="description">
                                    You can try setting this to "No" if you get a "SSL certificate problem" error,
                                    but that may make you vulnerable to man-in-the-middle attacks.
                                    For more information, see <a href="https://www.saotn.org/dont-turn-off-curlopt_ssl_verifypeer-fix-php-configuration/" target="_blank">here</a>.
                                </p>
                            </td>
                        </tr>                        
                    </table>
                </div>
                
                <div id="tabs-polling">
                	<p>
                	    <?php 
?>
                		Periodically this extension polls Mailgun for Bounced email addresses. <a href="<?php 
echo  $upgrade_url ;
?>">Upgrade to Pro</a> to also process Open and Click events.
                		<?php 
?>
					</p>
                    <table class="form-table">                                  
                        <tr valign="top">
                            <th>Polling Frequency</th>
                            <td>
                                <?php 
?>
                                <select disabled="disabled">
                                	<option>Every 30 minutes</option>
                                </select>
								<?php 
?>
                            	<p class="description">
                            		<?php 
?>
                            		<a href="<?php 
echo  $upgrade_url ;
?>">Upgrade to Pro</a> to be able to select a different polling frequency.
                            		<?php 
?>
                            	</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th>Last event processed</th>
                            <td>
                                <?php 
echo  $controls->print_date( $this->get_last_run() ) ;
?>
                                <?php 
$controls->button( 'trigger', 'Check now' );
?>
                                <?php 
$controls->button( 'reset', 'Reset last event time' );
?>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th>Next poll run</th>
                            <td>
                                <?php 
echo  $controls->print_date( wp_next_scheduled( SFMAILGUN_POLL_HOOK ) ) ;
?>
                            </td>
                        </tr>
                    </table>

                </div>

            </div>

            <p>
                <?php 
$controls->button( 'save', 'Save' );
?>
            </p>
        </form>
    </div>
    <?php 
@(include NEWSLETTER_DIR . '/tnp-footer.php');
?>
</div>
