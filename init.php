<?php

class Lemmy extends Plugin
{
    function about()
    {
        return [null, 'Add content to Lemmy feeds'];
    }

    function api_version()
    {
        return 2;
    }

    function init($host)
    {
    }
}
