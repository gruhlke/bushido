<?php
require_once(dirname(__FILE__) .'/base.class.php');

if (file_exists(dirname(__FILE__) .'/../config.inc.php')) {
	require_once(dirname(__FILE__) .'/../config.inc.php');
} else { // rely on pathing to find it for systems using shared bushido
	require_once('config.inc.php');
}

// -----------------------------------------------------------------------------
//	Email
// -----------------------------------------------------------------------------
class Email extends Base {
	// Send email and record to DB
	
	public $category = 0;	// type of email, used for email_log
	public $bounceback_email = null;
	public $use_iis_from_format = false;
	
	public function __construct($category) {
		parent::__construct(false);
		
		if ($category and is_numeric($category)) {
			// Category ID passed
			$this->category = $category;
		} elseif ($category) {
			// Translate from category code
			$this->category = $this->get_category_id($category);
		}
		
		$this->bounceback_email = (defined('BUSHIDO_BOUNCEBACK_EMAIL') ? BUSHIDO_BOUNCEBACK_EMAIL : null);
	}
	
	public function get_category_id($category_code) {
		// Return category ID for category code
		
		$sql = "SELECT id FROM email_categories ".
			"WHERE code = ". $this->db->qstr($category_code);
		$results = $this->db->Execute($sql);
		if ($this->db->results_check($results, true)) {
			return $results->fields['id'];
		} else {
			return 0;	// "Other"
		}
	}
	
	protected function get_environment() {
		return "TEST";
	}
	
	public function get_from($headers) {
		// Find 'from:' address in headers
		
		if (preg_match("/From: (.*@.*\.\w{2,4})/i", $headers, $matches)) {
			return $matches[1];
		} else {
			return "";
		}
	}
	
	public function log_email($to, $subject, $body, $headers) {
		// Log Email in DB
		
		$sql = "INSERT INTO email_log (recipient, sender, subject, body, category, send_date) VALUES (".
			$this->db->qstr($to) .", ".
			$this->db->qstr($this->get_from($headers)) .", ".
			$this->db->qstr($subject) .", ".
			$this->db->qstr($body) .", ".
			$this->db->qstr($this->category) .", ".
			$this->db->DBTimeStamp(time()) . 
		")";
			
		$results = $this->db->Execute($sql);
		
		if ($this->db->results_check($results)) {
			return $this->db->Insert_ID();
		} else {
			$this->error('Problem encountered logging email in database');
			return false;
		}
	}
	
	public function send($to, $subject, $body, $headers=null, $log_subject=null, $log_body=null) {
		global $DEV_ALLOW_EMAIL_LIST;
		
		// Send email to $to and record to DB
		// Log different subject or body (for security) if log_subject or log_body are passed
		
		if ($to == null or $subject == null or $body == null) {
			$this->error('Unable to send email: missing to, subject, or body');
			return false;
		}
		
		if ($log_subject === null) {
			$log_subject = $subject;
		}
		if ($log_body === null) {
			$log_body = $body;
		}
		
		// Dev environment setup
		if (DEV_ENVIRONMENT) {
			$subject = $this->get_environment() . " - ". $subject;

			// set $DEV_ALLOW_EMAIL_LIST to a list of allowed addresses to allow non-VSI addresses to be emailed from a DEV environment
			if (!stristr($to, "visionary.com") && (!isset($DEV_ALLOW_EMAIL_LIST) || !in_array(trim($to), $DEV_ALLOW_EMAIL_LIST))) {
				$to = BUSHIDO_DEV_EMAIL;
			}
		}
		
		if ($headers == null) {
			$headers = "From: ". BUSHIDO_FROM_EMAIL ."\n";
		}
		
		// Send Email
		$mailed = mail($to, $subject, $body, $headers, ($this->bounceback_email !== null ? "-f" . $this->bounceback_email : null));
		if ($mailed) {
			// Log email
			return $this->log_email($to, $log_subject, $log_body, $headers);
		} else {
			trigger_error('Failed sending mail to '. $to, E_USER_WARNING);
			$this->error('Unable to send mail to '. $to);
			return false;
		}
	}
	
	public function send_attachment($to, $subject, $body, $fromname, $fromemail, $filename, $data, $contenttype, $addheaders=null) {
		/* inputs:
		 * $to - recipient of the mail
		 * $subject - subject of the mail
		 * $body - body of the message
		 * $fromname - name the email should appear to come from
		 * $fromemail - reply-to address
		 * $filename - name of the attachment
		 * $data - contents to be attached ($pdfcode, $csvdata, fread($file), etc)
		 * $contenttype - content type of the attachment (application/pdf, application/octetstream, etc)
		 * $addheaders - additional headers (cc, bcc, etc)
		 *
		 * returns: true on success, false otherwise
		 */

		$OB = "----=_OuterBoundary_000";
		$IB = "----=_InnerBoundary_001";
		$from_name = $fromname;
		$email_from = $fromemail;
		
		$headers = "From: " . ($this->use_iis_from_format ? $email_from : $from_name . " <" . $email_from . ">") . "\n";
		$headers .= "Reply-To: <$email_from>\n";
		$headers .= "MIME-Version: 1.0\n";
		$headers .= "X-Sender: $from_name<$email_from>\n";
		$headers .= "X-Mailer: PHP / ".phpversion()."\n";
		$headers .= "X-Priority: 3\n"; //1 UrgentMessage, 3 Normal
		$headers .= "Return-Path: <$email_from>\n";
		$headers .= "Content-Type: multipart/mixed;\n\tboundary=\"".$OB."\"\n";
		$headers .= $addheaders;
		
		//Messages start with text/html alternatives in OB
		$message = "This is a multi-part message in MIME format.\n";
		$message .= "\n--".$OB."\n";
		$message .= "Content-Type: multipart/alternative;\n\tboundary=\"".$IB."\"\n\n";

		//plaintext section
		$message .= "\n--".$IB."\n";
		$message .= "Content-Type: text/plain; charset=\"iso-8859-1\"\n";
		$message .= "Content-Transfer-Encoding: quoted-printable\n";
		$message .= "\n";

		// your text goes here
		$message .= $body;
		$message .= "\n";

		// end of IB
		$message .= "\n--".$IB."--\n";
		
		$message .= "\n--".$OB."\n";
		$message .="Content-Type: ".$contenttype.";\n\tname=\"".$filename."\"\n";
		$message .="Content-Transfer-Encoding: base64\n";
		$message .="Content-Disposition: attachment;\n\tfilename=\"".$filename."\"\n\n";
		
		//file goes here
		$FileContent = chunk_split(base64_encode($data));
		$message .= $FileContent;
		$message .= "\n\n";
		
		$message .= "\n";
		//message ends
		$message .= "\n--".$OB."--\n";

		// Send Email
		return $this->send($to, $subject, $message, $headers);		
	}
	
	public function send_with_attachments($to, $subject, $html_body, $text_body, $fromname, $fromemail, $attachments = array(), $addheaders=null) {
		/* inputs:
		 * $to - recipient of the mail
		 * $subject - subject of the mail
		 * $html_body - html body of the message (optional - pass empty or null)
		 * $text_body - text body of the message (optional - pass empty or null)
		 * $fromname - name the email should appear to come from
		 * $fromemail - reply-to address
		 * $attachments - absolute path list of attachments.  can optionally pass as a keyed array where the key is the filename you want the attachement to be named.
		 * $addheaders - additional headers (cc, bcc, etc)
		 *
		 * returns: true on success, false otherwise
		 */

		// may have only passed in a single file
		$attachments = (!is_array($attachments) ? array($attachments) : $attachments);
		 
		$OB = "----=_OuterBoundary_000";
		$IB = "----=_InnerBoundary_001";
		$from_name = $fromname;
		$email_from = $fromemail;
		
		$headers = "From: " . ($this->use_iis_from_format ? $email_from : $from_name . " <" . $email_from . ">") . "\n";
		$headers .= "Reply-To: <$email_from>\n";
		$headers .= "MIME-Version: 1.0\n";
		$headers .= "X-Sender: $from_name<$email_from>\n";
		$headers .= "X-Mailer: PHP / ".phpversion()."\n";
		$headers .= "X-Priority: 3\n"; //1 UrgentMessage, 3 Normal
		$headers .= "Return-Path: <$email_from>\n";
		$headers .= "Content-Type: multipart/mixed;\n\tboundary=\"".$OB."\"\n";
		$headers .= $addheaders;
		
		//Messages start with text/html alternatives in OB
		$message = "This is a multi-part message in MIME format.\n";
		$message .= "\n--".$OB."\n";
		$message .= "Content-Type: multipart/alternative;\n\tboundary=\"".$IB."\"\n\n";

		if ($html_body != '') { 
			//plaintext section
			$message .= "\n--".$IB."\n";
			$message .= "Content-Type: text/html; charset=\"iso-8859-1\"\n";
			$message .= "Content-Transfer-Encoding: 7bit\n";
			$message .= "\n";

			// your text goes here
			$message .= $html_body;
			$message .= "\n";
		}
		
		if ($text_body != '') { 
			//plaintext section
			$message .= "\n--".$IB."\n";
			$message .= "Content-Type: text/plain; charset=\"iso-8859-1\"\n";
			$message .= "Content-Transfer-Encoding: quoted-printable\n";
			$message .= "\n";

			// your text goes here
			$message .= $text_body;
			$message .= "\n";
		}

		// end of IB
		$message .= "\n--".$IB."--\n";
		
		foreach ($attachments as $alternate_name => $attachment) {
			$file_name = basename((strpos($alternate_name, '.') !== false ? $alternate_name : $attachment));
			$data = file_get_contents($attachment);
			$content_type = mime_content_type($attachment);
			
			$message .= "\n--".$OB."\n";
			$message .= "Content-Type: " . $content_type . ";\n\tname=\"" . $file_name . "\"\n";
			$message .= "Content-Transfer-Encoding: base64\n";
			$message .= "Content-Disposition: attachment;\n\tfilename=\"" . $file_name . "\"\n\n";
			
			
			//file goes here
			$FileContent = chunk_split(base64_encode($data));
			$message .= $FileContent;
			$message .= "\n";
		}
		
		$message .= "\n";
		
		//message ends
		$message .= "\n--".$OB."--\n";

		// Send Email
		return $this->send($to, $subject, $message, $headers);		
	}	
}

?>
