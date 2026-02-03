<?php
$record = $record ?? [];
$degreeLevel = htmlspecialchars($record['degree_level'] ?? '', ENT_QUOTES);
$institution = htmlspecialchars($record['institution'] ?? '', ENT_QUOTES);
$field = htmlspecialchars($record['field_of_study'] ?? '', ENT_QUOTES);
$graduationYear = htmlspecialchars($record['graduation_year'] ?? '', ENT_QUOTES);
$grade = htmlspecialchars($record['grade'] ?? '', ENT_QUOTES);
$remarks = htmlspecialchars($record['remarks'] ?? '', ENT_QUOTES);
?>
<div class="card border-0 shadow-sm profile-repeatable" data-repeatable="academic">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="fw-semibold mb-0">Academic Record</h6>
      <button type="button" class="btn btn-sm btn-outline-danger" data-action="remove-repeatable">
        <i class="fas fa-times me-1"></i>Remove
      </button>
    </div>
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Degree Level</label>
        <input type="text" class="form-control" name="academic_degree_level[]" placeholder="e.g. Bachelor's" value="<?= $degreeLevel ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Institution</label>
        <input type="text" class="form-control" name="academic_institution[]" placeholder="University Name" value="<?= $institution ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Field of Study</label>
        <input type="text" class="form-control" name="academic_field[]" placeholder="Computer Science" value="<?= $field ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Graduation Year</label>
        <input type="number" min="1950" max="2100" class="form-control" name="academic_graduation_year[]" placeholder="2024" value="<?= $graduationYear ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Grade / CGPA</label>
        <input type="text" class="form-control" name="academic_grade[]" placeholder="3.8 GPA" value="<?= $grade ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Remarks</label>
        <input type="text" class="form-control" name="academic_remarks[]" placeholder="Scholarship, Honors, etc." value="<?= $remarks ?>">
      </div>
    </div>
  </div>
</div>
