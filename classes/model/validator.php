<?php

namespace Foolz\FoolFuuka\Plugins\SpamGuard\Model;

use Foolz\Inet\Inet;
use Foolz\FoolFrame\Model\Context;
use Foolz\FoolFrame\Model\DoctrineConnection;
use Foolz\FoolFrame\Model\Model;
use Foolz\FoolFrame\Model\Preferences;
use Symfony\Component\HttpFoundation\Request;

class Validator extends Model
{
    /**
     * @var DoctrineConnection
     */
    protected $dc;

    /**
     * @var Preferences
     */
    protected $preferences;

    public function __construct(Context $context)
    {
        parent::__construct($context);

        $this->dc = $context->getService('doctrine');
        $this->preferences = $context->getService('preferences');
    }

    public function checkComment($object)
    {
        $request = Request::createFromGlobals();
        $comment = $object->getObject();

        $this->processCIDR($request, $comment->comment);

        if ($this->preferences->get('foolfuuka.plugins.spam_guard.enable_spooky') && false === $comment->ghost_exist) {
            throw new \Foolz\FoolFuuka\Model\CommentSendingRequestCaptchaException;
        }

        if ($this->preferences->get('foolfuuka.plugins.spam_guard.enable_stopforumspam')) {
            $this->processSFS($request, $comment->comment);
        }

        if ($this->preferences->get('foolfuuka.plugins.spam_guard.enable_akismet')) {
            $this->processAkismet($request, $comment->comment);
        }

        $tor_limits = $this->preferences->get('foolfuuka.plugins.spam_guard.tor_limits', '');
        if ($tor_limits !== '' && $tor_limits !== 'none') {
            if ($this->isTorConnection($request, Inet::dtop($comment->poster_ip))) {
                switch ($tor_limits) {
                    case 'captcha':
                        throw new \Foolz\FoolFuuka\Model\CommentSendingRequestCaptchaException;
                        break;
                    case 'noimage':
                        if($request->files->count()) {
                            throw new \Foolz\FoolFuuka\Model\CommentSendingBannedException(_i('Posting images via Tor has been disabled.'));
                        }
                        break;
                    case 'nopost':
                        throw new \Foolz\FoolFuuka\Model\CommentSendingBannedException(_i('Posting via Tor has been disabled.'));
                        break;
                    default:
                        break;
                }
            }
        }
    }

    public function processAkismet($request, $comment)
    {
        $connector = new \Riv\Service\Akismet\Connector\Curl();
        $akismet = new \Riv\Service\Akismet\Akismet($connector);

        $key = $this->preferences->get('foolfuuka.plugins.spam_guard.akismet_key');
        $url = $this->preferences->get('foolfuuka.plugins.spam_guard.akismet_url');
        if ($key && $url && $akismet->keyCheck($key, $url)) {
            $data = [
                'content_type' => 'comment',
                'user_ip' => Inet::dtop($comment->poster_ip),
                'user_agent' => $request->headers->get('User-Agent'),
                'referrer' => $request->headers->get('Referer'),
                'comment_author' => $comment->name,
                'comment_author_email' => $comment->email,
                'comment_content' => $comment->comment
            ];

            if ($akismet->check($data)) {
                throw new \Foolz\FoolFuuka\Model\CommentSendingRequestCaptchaException;
            }
        }
    }

    public function processCIDR($request, $comment)
    {
        if (preg_match('/^(([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]).){3}([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/', $comment->poster_ip)) {
            $range = [];

            try {
                $range = preg_split('/\r\n|\r|\n/', $this->preferences->get('foolfuuka.plugins.spam_guard.ban_ranges', []));
            } catch (\Exception $e) {
            }

            foreach ($range as $cidr) {
                if ($this->isMatchCIDR(Inet::dtop($comment->poster_ip), $cidr)) {
                    throw new \Foolz\Foolfuuka\Model\CommentSendingBannedException('We were unable to process your comment at this time.');
                }
            }
        }
    }

    public function processSFS($request, $comment)
    {
        $check = $this->dc->qb()
            ->select('1')
            ->from($this->dc->p('plugin_fu_spam_guard_sfs'), 'sfs')
            ->where('ip_addr_n = :ip_addr_n')
            ->setParameter(':ip_addr_n', $comment->poster_ip)
            ->execute()
            ->fetchAll();

        if (count($check) !== 0) {
            throw new \Foolz\FoolFuuka\Model\CommentSendingRequestCaptchaException;
        }
    }

    public function isMatchCIDR($ip, $range)
    {
        list($subnet, $bits) = explode('/', $range);

        $ip = ip2long($ip);
        $netmask = -1 << (32 - $bits);

        $subnet = ip2long($subnet);
        $subnet &= $netmask;

        return ($ip & $netmask) == $subnet;
    }


    /**
     * Simple way to detect Tor exit connection by using TorDNSEL.
     * This won't work if the server isn't aware of it's true public IP.
     * If that's the case replace $request->server->get('SERVER_ADDR') with some other public IP.
     * @param $request
     * @param string $poster_ip
     * @return bool true if Tor connection
     */
    public function isTorConnection($request, $poster_ip)
    {
        // TorDNSEL doesn't support IPv6 yet
        if (filter_var($poster_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) || filter_var($request->server->get('SERVER_ADDR'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return false;
        }
        if (gethostbyname(implode('.', array_reverse(explode('.', $poster_ip))) . '.80.' . implode('.', array_reverse(explode('.', $request->server->get('SERVER_ADDR')))) . '.ip-port.exitlist.torproject.org') === '127.0.0.2') {
            return true;
        }
        return false;
    }

}
