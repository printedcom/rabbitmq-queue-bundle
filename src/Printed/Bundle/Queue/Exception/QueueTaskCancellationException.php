<?php

namespace Printed\Bundle\Queue\Exception;

use RuntimeException;

/**
 * Exception used to cancel consumer's execution from anywhere in its code, due to the
 * fact, that a queue task has been cancelled.
 */
class QueueTaskCancellationException extends RuntimeException
{

}
