<?php

declare(strict_types=1);

return [
    /*
    | The Anti-Corruption Commission holds a special role in the workflow:
    | it runs the intake review queue (Submitted → UnderReview → Accepted)
    | and is the mandatory assignee for any grievance categorized as
    | "corruption". Identified by acronym so demo/prod can differ on org ID.
    |
    | The old `restricted_types` array has been removed. Routing is now
    | driven by the `category` field (corruption vs. administrative) set
    | during the categorization step, enforced by GrievanceWorkflow.
    */
    'acc_acronym' => env('GRM_ACC_ACRONYM', 'ACC'),
];
