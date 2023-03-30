<?php

namespace Printed\Bundle\Queue\Exception\Consumer;

use RuntimeException;

/**
 * Throwing this exception within a queue consumer will prevent it from running again by maxing out the task attempts.
 * The message is then logged in the log file and the task is exited.
 */
class QueueFatalErrorException extends RuntimeException
{

}
