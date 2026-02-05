<?php

declare(strict_types=1);

namespace Twitter\Tweet\UI\Web\Tweets;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TweetPageController extends AbstractController
{
    #[Route('/t/{tweetId}', name: 'TweetPage', methods: ['GET'])]
    public function showTweetPage(string $tweetId): Response
    {
        return $this->render('page/tweetpage.html.twig');
    }
}
