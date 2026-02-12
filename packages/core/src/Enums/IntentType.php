<?php

declare(strict_types=1);

namespace Botovis\Core\Enums;

/**
 * The type of intent resolved from user's natural language message.
 */
enum IntentType: string
{
    /** User wants to perform a CRUD action */
    case ACTION = 'action';

    /** User is asking a question / needs clarification */
    case QUESTION = 'question';

    /** LLM needs more information from the user */
    case CLARIFICATION = 'clarification';

    /** Message is not related to any known operation */
    case UNKNOWN = 'unknown';
}
