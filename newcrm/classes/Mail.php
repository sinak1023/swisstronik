<?php


class Mail
{

    public function Send($to, $subject, $message, $file)
    {
        $encoded_content = "";
        if ($file !== null) {
            $file = fopen($file, "r");
            if ($file == false) {
                return "Error in opening file";
                exit();
            }


            # Read the file into a variable
            $size = filesize($file);
            $content = fread($file, $size);

            # encode the data for safe transit
            # and insert \r\n after every 76 chars.
            $encoded_content = chunk_split(base64_encode($content));
        }
        # Get a random 32 bit number using time() as seed.
        $num = md5(time());

        # Define the main headers.
        $header = "MIME-Version: 1.0" . "\r\n";
        $header .= "Content-type:text/html;charset=UTF-8" . "\r\n";


        $header .= 'From: Kardana<notification@'.$_SERVER['HTTP_HOST'].'>' . "\r\n";
        $header .= 'Cc: notification@'.$_SERVER['HTTP_HOST']. "\r\n";
        if ($file !== null) {
            # Define the attachment section
            $header .= "Content-Type:  multipart/mixed; ";
            $header .= "name=\"" . basename($file) . "\"\r\n";
            $header .= "Content-Transfer-Encoding:base64\r\n";
            $header .= "Content-Disposition:attachment; ";
            $header .= "filename=\"" . basename($file) . "\"\r\n\n";
            $header .= "$encoded_content\r\n";
            $header .= "--$num--";
        }
        # Send email now
        try {
            $retval = mail($to, $subject, $message, $header);

            return $retval;

        } catch (Exception $exception) {

            return $exception;
        }

    }

}