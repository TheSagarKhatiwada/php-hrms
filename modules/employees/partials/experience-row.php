<?php
$record = $record ?? [];
$organization = htmlspecialchars($record['organization'] ?? '', ENT_QUOTES);
$jobTitle = htmlspecialchars($record['job_title'] ?? '', ENT_QUOTES);
$startDate = htmlspecialchars($record['start_date'] ?? '', ENT_QUOTES);
$endDate = htmlspecialchars($record['end_date'] ?? '', ENT_QUOTES);
$responsibilities = htmlspecialchars($record['responsibilities'] ?? '', ENT_QUOTES);
$achievements = htmlspecialchars($record['achievements'] ?? '', ENT_QUOTES);
$isCurrent = !empty($record['currently_working']);
?>
<div class="card border-0 shadow-sm profile-repeatable" data-repeatable="experience">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="fw-semibold mb-0">Experience Entry</h6>
      <button type="button" class="btn btn-sm btn-outline-danger" data-action="remove-repeatable">
        <i class="fas fa-times me-1"></i>Remove
      </button>
    </div>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Organization</label>
        <input type="text" class="form-control" name="experience_organization[]" placeholder="Company / Institution" value="<?= $organization ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Job Title</label>
        <input type="text" class="form-control" name="experience_job_title[]" placeholder="Role" value="<?= $jobTitle ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Start Date</label>
        <input type="date" class="form-control" name="experience_start_date[]" value="<?= $startDate ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">End Date</label>
        <input type="date" class="form-control experience-end-date" name="experience_end_date[]" value="<?= $isCurrent ? '' : $endDate ?>" <?= $isCurrent ? 'disabled' : '' ?>>
      </div>
      <div class="col-md-3">
        <label class="form-label">Currently Working</label>
        <div class="form-check form-switch mt-1">
          <input type="hidden" name="experience_currently_working[]" value="<?= $isCurrent ? '1' : '0' ?>" class="experience-current-hidden">
          <input class="form-check-input experience-current-checkbox" type="checkbox" <?= $isCurrent ? 'checked' : '' ?>>
        </div>
      </div>
      <div class="col-md-12">
        <label class="form-label">Key Responsibilities</label>
        <textarea class="form-control" name="experience_responsibilities[]" rows="2" placeholder="Summarize core responsibilities"><?= $responsibilities ?></textarea>
      </div>
      <div class="col-md-12">
        <label class="form-label">Achievements</label>
        <textarea class="form-control" name="experience_achievements[]" rows="2" placeholder="Awards, outcomes, highlights"><?= $achievements ?></textarea>
      </div>
    </div>
  </div>
</div>
