<?php

namespace Printed\Bundle\Queue\Exception;

/**
 * Exception used to cancel consumer's execution from anywhere in its code, due to the
 * fact, that a queue task has been cancelled.
 */
class QueueTaskCancellationException extends \RuntimeException
{

}
