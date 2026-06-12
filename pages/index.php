<?php

use Redaxo\Core\Backend\Controller;
use Redaxo\Core\View\View;

echo View::title('YRewrite');

Controller::includeCurrentPageSubPath();
