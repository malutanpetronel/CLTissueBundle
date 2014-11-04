<?php

namespace CL\Bundle\TissueBundle\Validator\Constraints;

use CL\Tissue\Adapter\AdapterInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\FileValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates whether a value is a valid file and does not contain any viruses
 */
class CleanFileValidator extends FileValidator
{
    /**
     * @var AdapterInterface
     */
    private $scanningAdapter;

    /**
     * @param AdapterInterface $scanningAdapter
     */
    public function __construct(AdapterInterface $scanningAdapter)
    {
        $this->scanningAdapter = $scanningAdapter;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof CleanFile) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\CleanFile');
        }

        if (null === $value || '' === $value) {
            return;
        }

        $path = $value instanceof File ? $value->getPathname() : (string) $value;
        $clientFilename = $value instanceof UploadedFile ? $value->getClientOriginalName() : $path;

        if ($constraint->restrictedFilename && (stristr($clientFilename, '/') || !preg_match($constraint->restrictedFilenameRegex, $clientFilename))) {
            $this->buildViolation($constraint->invalidFilenameMessage)->addViolation();

            return;
        }

        if ($this->scanningAdapter->scan($path)->hasVirus()) {
            $this->buildViolation($constraint->virusDetectedMessage)->addViolation();

            return;
        }

        // IMPORTANT: we do the regular file-validation AFTER scanning the file!
        // This is because the regular validation will access the (possibly infected) file to determine readability/information
        // and we obviously don't want that to happen if we are dealing with a virus.
        if ($constraint->useParentValidation !== false) {
            parent::validate($value, $constraint);
        }
    }
}
