<?php
/**
* Indexed Gtk2 combo box similar to the HTML select box.
*
* PHP Versions 5
*
* @category Gtk2
* @package  Gtk2_FileDrop
* @author   Christian Weiske <cweiske@php.net>
* @license  http://www.php.net/license PHP License
* @version  CVS: $Id$
* @link     http://pear.php.net/package/Gtk2_FileDrop
*/

require_once 'MIME/Type.php';
require_once 'PEAR.php';

/**
* A class which makes it easy to
* make a GtkWidget accept the dropping
* of files or folders
*
* @category Gtk2
* @package  Gtk2
* @author   Christian Weiske <cweiske@php.net>
* @license  http://www.php.net/license PHP License
* @link     http://pear.php.net/package/Gtk2_FileDrop
*
* @todo
* - reject files when moving the dragging mouse over the widget,
*   just like opera does how does this work?
*   I don't know, but I suppose I should
*
* @example
* Usage:
* Simply change the text of a widget
*  (accept files with MIME-Types text/plain and text/html
*     and files with .sgml extension):
*
*  Gtk2_FileDrop::attach($entry, array('text/plain', 'text/html', '.sgml'));
*
*
* Call a callback, and don't change the text (accept directories only):
*  Gtk2_FileDrop::attach(
*    $entry, array( 'inode/directory'), array( $this, 'callback'), false
*  );
*/
class Gtk2_FileDrop
{
    /**
    * A FileDrop error code.
    * returned if the widget doesn't support drops
    */
    const WIDGET_NOT_SUPPORTED = 1;



    /**
    * Prepares a widget to accept file drops.
    *
    * @param GtkWidget $widget      The widget which shall accept files
    * @param array     $arTypes     List of MIME-Types to accept OR extensions,
    *                               beginning with a dot "."
    * @param mixed     $objCallback Callback to call when a drop with
    *                               valid files happened
    * @param boolean   $bSetText    If the widget's text/label/content shall
    *                               be changed automatically
    *
    * @return boolean If all was ok
    */
    static function attach($widget, $arTypes, $objCallback = null, $bSetText = true)
    {
        $widget->drag_dest_set(
            Gtk::DEST_DEFAULT_ALL,
            array(array('text/uri-list', 0, 0)),
            Gdk::ACTION_COPY | Gdk::ACTION_MOVE
        );

        $fd = new Gtk2_FileDrop( $arTypes, $objCallback, $bSetText);
        $widget->connect('drag-data-received', array($fd, 'dragDataReceived'));

        return true;
    }//static function attach(...)



    /**
    * Use attach() instead.
    *
    * @param array    $arTypes     Array of accepted types
    * @param callback $objCallback Callback method
    * @param boolean  $bSetText    If the text shall be set
    *
    * @return void
    */
    private function Gtk2_FileDrop($arTypes, $objCallback = null, $bSetText = true)
    {
        $this->arTypes     = $arTypes;
        $this->objCallback = $objCallback;
        $this->bSetText    = $bSetText;
    }//private function Gtk2_FileDrop...)



    /**
    * Prepares a widget to accept directories only.
    * Just a shortcut for the exhausted programmer.
    *
    * @param GtkWidget $widget The widget which shall accept directories
    *
    * @return boolean If all was ok
    */
    static function attachDirectory($widget)
    {
        return self::attach($widget, array('inode/directory'));
    }//static function attachDirectory($widget)



    /**
    * Data have been dropped over the widget.
    *
    * @param GtkWidget      $widget  The widget on which the data have been dropped
    * @param GdkDragContext $context The context of the drop
    * @param int            $x       X position
    * @param int            $y       Y position
    * @param mixed          $data    data
    * @param int            $info    Info parameter (0 in our case)
    * @param int            $time    The time on which the event happened
    *
    * @return void
    */
    function dragDataReceived($widget, $context , $x, $y, $data , $info, $time)
    {
        $arData       = explode("\n", $data->data);
        $arAccepted   = array();
        $arRejected   = array();
        $bDirectories = false;
        foreach ($arData as $strLine) {
            $strLine = trim($strLine);
            if ($strLine == '') {
                continue;
            }
            $strFile     = self::getPathFromUrilistEntry($strLine);
            $strFileMime = self::getMimeType($strFile);
            $bAccepted   = false;
            foreach ($this->arTypes as $strType) {
                if ($strType == 'inode/directory') {
                    $bDirectories = true;
                }
                if (($strType[0] == '.'
                    && self::getFileExtension($strFile) == $strType
                )
                 || $strType == $strFileMime
                 || (strpos($strType, '/') !== false
                     && MIME_Type::wildcardMatch($strType, $strFileMime)
                )
                ) {
                    $arAccepted[] = $strFile;
                    $bAccepted    = true;
                    break;
                }
            }//foreach type
            if (!$bAccepted) {
                $arRejected[] = $strFile;
            }
        }//foreach line

        //make directories from the files if dirs are accepted
        //this is done here to give native directories first places on the list
        if ($bDirectories && count($arRejected) > 0) {
            foreach ($arRejected as $strFile) {
                $arAccepted[] = dirname($strFile);
            }
        }

        if (count($arAccepted) == 0) {
            //no matching files
            return;
        }

        if ($this->bSetText) {
            $strClass = get_class($widget);
            switch ($strClass) {
            case 'GtkEntry':
            case 'GtkLabel':
                $widget->set_text($arAccepted[0]);
                break;
            case 'GtkButton':
            case 'GtkToggleButton':
            case 'GtkCheckButton':
            case 'GtkRadioButton':
                $childs = $widget->get_children();
                $child  = $childs[0];
                if (get_class($child) == 'GtkLabel') {
                    $child->set_text($arAccepted[0]);
                } else {
                    trigger_error('No label found on widget.');
                }
                break;
            case 'GtkCombo':
                $entry = $widget->entry;
                $entry->set_text($arAccepted[0]);
                break;
            case 'GtkFileSelection':
                $widget->set_filename($arAccepted[0]);
                break;
            case 'GtkList':
                foreach ($arAccepted as $strFile) {
                    $items[] = new GtkListItem($strFile);
                }
                $widget->append_items($items);
                $widget->show_all();
                break;
            default:
                PEAR::raiseError(
                    'Widget class "' . $strClass . '" is not supported',
                    self::WIDGET_NOT_SUPPORTED,
                    PEAR_ERROR_TRIGGER,
                    E_USER_WARNING
                );
                break;
            }
        }//if bSetText

        if ($this->objCallback !== null) {
            call_user_func($this->objCallback, $widget, $arAccepted);
        }//objCallback !== null
    }//function dragDataReceived($widget, $context , $x, $y, $data , $info, $time)



    /**
    * Converts a file path gotten from a text/uri-list
    * drop to a usable local filepath.
    *
    * Php functions like parse_url can't be used as it is
    * likely that the dropped URI is no real URI but a
    * strange thing which tries to look like one
    * See the explanation at:
    * http://gtk.php.net/manual/en/tutorials.filednd.urilist.php
    *
    * @param string $strUriFile The line from the uri-list
    *
    * @return string The usable local filepath
    */
    static function getPathFromUrilistEntry($strUriFile)
    {
        $strUriFile = urldecode($strUriFile);//should be URL-encoded
        $bUrl       = false;
        if (substr($strUriFile, 0, 5) == 'file:') {
            //(maybe buggy) file protocol
            if (substr($strUriFile, 0, 17) == 'file://localhost/') {
                //correct implementation
                $strFile = substr($strUriFile, 16);
            } else if (substr($strUriFile, 0, 8) == 'file:///') {
                //no hostname, but three slashes - nearly correct
                $strFile = substr($strUriFile, 7);
            } else if ($strUriFile[5] == '/') {
                //theoretically, the hostname should be the first
                //but no one implements it
                $strUriFile = substr($strUriFile, 5);
                for ($n = 1; $n < 5; $n++) {
                    if ($strUriFile[$n] != '/') {
                        break;
                    }
                }
                $strUriFile = substr($strUriFile, $n - 1);
                if (!file_exists($strUriFile)) {
                    //perhaps a correct implementation with hostname???
                    $strUriFileNoHost = strstr(substr($strUriFile, 1), '/');
                    if (file_exists($strUriFileNoHost)) {
                        //seems so
                        $strUriFile = $strUriFileNoHost;
                    }
                }
                $strFile = $strUriFile;
            } else {
                //NO slash after "file:" - what is that for a crappy program?
                $strFile = substr($strUriFile, 5);
            }
        } else if (strstr($strUriFile, '://')) {
            //real protocol, but not file
            $strFile = $strUriFile;
            $bUrl    = true;
        } else {
            //local file?
            $strFile = $strUriFile;
        }
        if (!$bUrl && $strFile[2] == ':' && $strFile[0] == '/') {
            //windows file path
            $strFile = str_replace('/', '\\', substr($strFile, 1));
        }
        return $strFile;
    }//static function getPathFromUrilistEntry($strUriFile)



    /**
    * Returns the extension if a filename
    * including the leading dot.
    *
    * @param string $strFile The filename
    *
    * @return string The extension with a leading dot
    */
    static function getFileExtension($strFile)
    {
        $strExt = strrchr($strFile, '.');
        if ($strExt == false) {
            return '';
        }
        $strExt = str_replace('\\', '/', $strExt);
        if (strpos($strExt, '/') !== false) {
            return '';
        }
        return $strExt;
    }//static function getFileExtension($strFile)



    /**
    * Determines the mime-type for the given file.
    *
    * @param string $strFile The file name
    *
    * @return string The MIME type or FALSE in the case of an error
    */
    static function getMimeType($strFile)
    {
        //MIME_Type doesn't return the right type for directories
        //The underlying functions MIME_Type used don't return it right,
        //so there is no chance to fix MIME_Type itself
        if ((file_exists($strFile) && is_dir($strFile))
          || substr($strFile, -1) == '/') {
            return 'inode/directory';
        }
        $strMime = MIME_Type::autoDetect($strFile);
        if (!PEAR::isError($strMime)) {
            return $strMime;
        }

        //determine by extension | as MIME_TYPE doesn't support this,
        // I have to do this myself
        $strExtension = self::getFileExtension($strFile);
        switch ($strExtension) {
        case '.txt' :
            $strType = 'text/plain';
            break;
        case '.gif' :
            $strType = 'image/gif';
            break;
        case '.jpg' :
        case '.jpeg':
            $strType = 'image/jpg';
            break;
        case '.png' :
            $strType = 'image/png';
            break;
        case '.xml' :
            $strType = 'text/xml';
            break;
        case '.htm' :
        case '.html':
            $strType = 'text/html';
            break;
        default:
            $strType = false;
            break;
        }
        return $strType;
    }//static function getMimeType($strFile)

}//class Gtk2_FileDrop
?>
