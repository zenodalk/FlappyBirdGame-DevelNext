<?php
namespace app\forms;

use php\gui\framework\AbstractForm;
use php\gui\event\UXEvent; 
use php\gui\event\UXKeyEvent; 
use php\game\event\UXCollisionEvent; 
use php\lib\arr; 
use php\lib\bin; 
use php\lib\char; 
use php\lib\fs; 
use php\lib\str; 
use php\lib\num; 
use php\lib\reflect; 
use php\io\Stream; 
use php\io\File; 
use php\io\IOException; 
use php\io\FileStream; 
use php\io\MemoryStream; 
use php\io\ResourceStream; 
use php\net\NetStream; 
use php\util\Flow; 
use php\util\Locale; 
use php\util\Regex; 
use php\util\Configuration; 
use php\time\Time; 
use php\time\TimeZone; 
use php\time\TimeFormat; 
use php\net\URL; 
use php\net\Socket; 
use php\net\SocketException; 
use php\net\ServerSocket; 
use php\net\Proxy; 
use php\lang\Thread; 
use php\lang\Environment; 
use php\lang\Process; 
use php\lang\System; 
use php\lang\ThreadGroup; 
use php\lang\ThreadPool; 
use php\format\JsonProcessor; 
use facade\Json; 
use php\gui\UXNode; 
use php\gui\UXApplication; 
use php\gui\animation\UXAnimationTimer; 
use php\gui\layout\UXHBox; 
use php\gui\layout\UXAnchorPane; 
use php\gui\UXClipboard; 
use php\gui\paint\UXColor; 
use php\gui\event\UXContextMenuEvent; 
use php\gui\UXDialog; 
use php\gui\text\UXFont; 
use php\gui\UXGeometry; 
use php\gui\UXImage; 
use php\gui\UXMedia; 
use php\gui\UXMenu; 
use php\gui\UXMenuItem; 
use php\gui\UXButton; 
use php\gui\UXTooltip; 
use php\gui\UXToggleButton; 
use php\gui\UXToggleGroup; 
use php\gui\UXImageView; 
use php\gui\UXImageArea; 
use php\gui\UXSlider; 
use php\gui\UXSpinner; 
use php\gui\layout\UXVBox; 
use php\gui\UXTitledPane; 
use php\gui\layout\UXPanel; 
use php\gui\layout\UXFlowPane; 
use php\gui\UXForm; 
use php\gui\UXWindow; 
use ide\bundle\std\UXAlert; 
use php\gui\UXContextMenu; 
use php\gui\UXControl; 
use php\gui\UXDirectoryChooser; 
use php\gui\UXFileChooser; 
use php\gui\UXFlatButton; 
use php\gui\UXHyperlink; 
use php\gui\UXList; 
use php\gui\UXListView; 
use php\gui\UXComboBox; 
use php\gui\UXChoiceBox; 
use php\gui\UXLabel; 
use php\gui\UXLabelEx; 
use php\gui\UXLabeled; 
use php\gui\UXListCell; 
use php\gui\UXMediaPlayer; 
use php\gui\UXParent; 
use php\gui\UXPopupWindow; 
use php\gui\UXPasswordField; 
use php\gui\UXProgressIndicator; 
use php\gui\UXProgressBar; 
use php\gui\UXTab; 
use php\gui\UXTabPane; 
use php\gui\UXTreeView; 
use php\gui\UXTrayNotification; 
use php\gui\UXWebEngine; 
use php\gui\UXWebView; 
use php\gui\UXCell; 
use php\gui\UXColorPicker; 
use php\gui\UXCanvas; 
use php\gui\layout\UXStackPane; 
use php\gui\layout\UXPane; 
use php\gui\layout\UXScrollPane; 
use php\gui\event\UXDragEvent; 
use php\gui\event\UXMouseEvent; 
use php\gui\event\UXWebEvent; 
use php\gui\event\UXWindowEvent; 
use php\gui\framework\AbstractModule; 
use action\Animation; 
use action\Collision; 
use game\Jumping; 
use action\Element; 
use action\Geometry; 
use action\Media; 
use action\Score; 


class Proto extends AbstractForm
{

    /**
     * @event bottomTube.outside 
     **/
    function doBottomTubeOutside(UXEvent $event = null)
    {
        $event->sender->free();
    }

    /**
     * @event topTube.outside 
     **/
    function doTopTubeOutside(UXEvent $event = null)
    {
        
        $event->sender->free();
    }

    
    /**
     * @event bird.globalKeyDown-Space 
     **/
    function doBirdGlobalKeyDownSpace(UXKeyEvent $event = null)
    {
        global $gameRunning;

        $gameRunning = true;
        
        $event->sender->phys->gravity = [0.0, 9.0];
        $event->sender->phys->vspeed = -8;
        
        $this->instances('Proto.startLabel')->hide();
        
        Media::open('res://.data/audio/sfx_wing.wav', true, 'wingPlayer');
    }

    function gameOver() {
        global $gameRunning, $resultScore;

        $gameRunning = false;
        
        // Записываем итоговый счет в переменную.
        $resultScore = Score::get('global');
        
        $this->originForm('App')->game->phys->loadScene('GameOver');
        
        // обнуляем счет!
        Score::set('global', 0);
        
        Media::open('res://.data/audio/sfx_die.wav');
    }

    /**
     * @event bird.collision-bottomTube 
     **/
    function doBirdCollisionbottomTube(UXCollisionEvent $event = null)
    {
        $this->gameOver();
    }

    /**
     * @event bird.collision-topTube 
     **/
    function doBirdCollisiontopTube(UXCollisionEvent $event = null)
    {    
        $this->gameOver();
    }

    /**
     * @event bird.collision-coin 
     **/
    function doBirdCollisioncoin(UXCollisionEvent $event = null)
    {          
        // увеличиваем наш счет.
        Score::inc('global', 1);
        
        // уничтожаем монетку.
        $event->target->free();
        
        Media::open('res://.data/audio/sfx_point.wav');
    }

    /**
     * @event bird.collision-background 
     **/
    function doBirdCollisionbackground(UXCollisionEvent $event = null)
    {
        $this->gameOver();
    }

    /**
     * @event coin.step 
     **/
    function doCoinStep(UXEvent $event = null)
    {
        // Если монетка окажется на трубе, то из-за
        // своей твердости она будет выталкиваться
        // и ее скорость обнулиться, мы должны вернуть ей скорость
        
        if ($event->sender->phys->hspeed >= 0) {
            waitAsync(1000, function () use ($event) {
                Jumping::toGrid($event->sender, 1, 1);
                $event->sender->phys->hspeed = -5;
            });
        }   
    }

    /**
     * @event bird.collision-bigCoin 
     **/
    function doBirdCollisionbigCoin(UXCollisionEvent $event = null)
    {    
        // увеличиваем наш счет на 5.
        Score::inc('global', 5);
        
        // уничтожаем монетку.
        $event->target->free();
        
        Media::open('res://.data/audio/sfx_point.wav');
    }

    /**
     * @event bigCoin.step 
     **/
    function doBigCoinStep(UXEvent $event = null)
    {    
        if ($event->sender->phys->hspeed >= 0) {
            waitAsync(1000, function () use ($event) {
                Jumping::toGrid($event->sender, 1, 1);
                $event->sender->phys->hspeed = -5;
            });
        }
    }
}
