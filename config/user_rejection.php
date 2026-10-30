<?php

// Rejecting a farmer sign-up (MAO, Farmer Approvals): the reasons to pick from. The words (en / fil) are in
// lang/{en,fil}/user_rejection.php under reasons.<key>; the farmer's email gives the reason in their language, with the
// MAO's optional note under it. requires_text: the MAO must write the note (at least other_min_length characters).
return [
    'other_min_length' => 10,

    'reasons' => [
        'not_resident' => ['requires_text' => false],
        'unverified' => ['requires_text' => false],
        'rsbsa_mismatch' => ['requires_text' => false],
        'duplicate' => ['requires_text' => false],
        'incomplete' => ['requires_text' => false],
        'other' => ['requires_text' => true],
    ],
];
