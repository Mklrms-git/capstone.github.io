<?php
// View Medical History Modal - Combined file for both individual history view and history type view
// File: view_medical_history_modal.php

if (!defined('MHAVIS_EXEC')) {
    die('Direct access not permitted');
}
?>

<!-- View Medical History Modal (Individual Entry) -->
<div class="modal fade" id="viewMedicalHistoryModal" tabindex="-1" aria-labelledby="viewMedicalHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="viewMedicalHistoryModalLabel">
                    <i class="fas fa-history me-2"></i>Medical History Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewMedicalHistoryModalBody">
                <!-- Content will be populated by JavaScript -->
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- View History Type Modal (All entries of a specific type) -->
<div class="modal fade" id="viewHistoryTypeModal" tabindex="-1" aria-labelledby="viewHistoryTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="viewHistoryTypeModalLabel">
                    <i class="fas fa-history me-2"></i><span id="historyTypeTitle">Medical History</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewHistoryTypeModalBody" style="max-height: 70vh; overflow-y: auto;">
                <!-- Content will be populated by JavaScript -->
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Function to populate the view modal with medical history data
function populateViewHistoryModal(recordData) {
    const modalBody = document.getElementById('viewMedicalHistoryModalBody');
    
    // Format history type
    const historyType = recordData.history_type ? 
        recordData.history_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) : 
        'Unknown';
    
    // Format created date
    const createdDate = recordData.created_at ? new Date(recordData.created_at).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    }) : 'Not available';
    
    // Format created time
    const createdTime = recordData.created_at ? new Date(recordData.created_at).toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    }) : '';
    
    // Format updated date if different from created
    let updatedInfo = '';
    if (recordData.updated_at && recordData.created_at && 
        recordData.updated_at !== recordData.created_at &&
        recordData.updater_first_name) {
        const updatedDate = new Date(recordData.updated_at).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        const updaterRole = recordData.updater_role === 'Admin' ? 
            '<span class="badge bg-warning text-dark me-1">Admin</span>' : 
            '<span class="badge bg-info text-white me-1">Doctor</span>';
        updatedInfo = `
            <div class="col-md-6 text-md-end">
                <i class="fas fa-user-edit me-1"></i>
                <strong>Last updated by:</strong><br>
                ${updaterRole}
                ${escapeHtml(recordData.updater_first_name + ' ' + (recordData.updater_last_name || ''))}<br>
                <small class="text-muted">on ${updatedDate}</small>
            </div>
        `;
    } else {
        updatedInfo = `
            <div class="col-md-6 text-md-end">
                <i class="fas fa-calendar me-1"></i>
                <strong>Date:</strong><br>
                <small class="text-muted">${createdDate}</small>
            </div>
        `;
    }
    
    // Format recorder info
    let recorderHtml = '';
    let displayName = '';
    let displayRole = '';
    let specialization = '';
    
    if (recordData.creator_first_name && recordData.creator_first_name.trim()) {
        displayName = recordData.creator_first_name + ' ' + (recordData.creator_last_name || '');
        displayRole = recordData.creator_role || '';
    } else if (recordData.doctor_first_name && recordData.doctor_first_name.trim()) {
        displayName = recordData.doctor_first_name + ' ' + (recordData.doctor_last_name || '');
        displayRole = recordData.doctor_role || '';
        specialization = recordData.specialization || '';
    }
    
    if (displayName) {
        const roleBadge = displayRole === 'Admin' ? 
            '<span class="badge bg-warning text-dark me-1">Admin</span>' : 
            '<span class="badge bg-info text-white me-1">Doctor</span>';
        const specText = specialization ? 
            ` <span class="text-muted">(${escapeHtml(specialization)})</span>` : '';
        
        recorderHtml = `
            <div class="col-md-6">
                <i class="fas fa-user-md me-1"></i>
                <strong>Recorded by:</strong><br>
                ${roleBadge}
                ${escapeHtml(displayName)}${specText}<br>
                <small class="text-muted">on ${createdDate} ${createdTime ? 'at ' + createdTime : ''}</small>
            </div>
        `;
    } else {
        recorderHtml = `
            <div class="col-md-6">
                <i class="fas fa-user-md me-1"></i>
                <strong>Recorded by:</strong><br>
                <span class="text-muted">Unknown Recorder</span><br>
                <small class="text-muted">on ${createdDate} ${createdTime ? 'at ' + createdTime : ''}</small>
            </div>
        `;
    }
    
    // Format status badge
    const status = recordData.status || 'active';
    const statusBadge = status === 'active' ? 
        '<span class="badge bg-success">Active</span>' : 
        '<span class="badge bg-secondary">Inactive</span>';
    
    // Parse structured data if available
    let detailsHtml = '';
    if (recordData.structured_data) {
        try {
            const structuredData = typeof recordData.structured_data === 'string' 
                ? JSON.parse(recordData.structured_data) 
                : recordData.structured_data;
            detailsHtml = formatStructuredData(recordData.history_type, structuredData);
        } catch (e) {
            console.error('Error parsing structured data:', e);
            detailsHtml = recordData.history_details ? 
                `<div class="text-muted mt-2">${escapeHtml(recordData.history_details).replace(/\n/g, '<br>')}</div>` : 
                '<p class="text-muted fst-italic">No details provided</p>';
        }
    } else if (recordData.history_details) {
        detailsHtml = `<div class="text-muted mt-2">${escapeHtml(recordData.history_details).replace(/\n/g, '<br>')}</div>`;
    } else {
        detailsHtml = '<p class="text-muted fst-italic">No details provided</p>';
    }
    
    // Build the HTML content
    modalBody.innerHTML = `
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1">
                        <span class="history-type-badge bg-info text-white me-2">
                            ${escapeHtml(historyType)}
                        </span>
                        Medical History Entry
                    </h6>
                    <small class="text-muted">
                        Created: ${createdDate}${createdTime ? ' at ' + createdTime : ''}
                    </small>
                </div>
            </div>
        </div>
        
        <div class="mb-3">
            <strong><i class="fas fa-file-alt me-1"></i>Details:</strong>
            ${detailsHtml}
        </div>
        
        <div class="row mb-3">
            <div class="col-sm-4"><strong><i class="fas fa-info-circle me-1"></i>Status:</strong></div>
            <div class="col-sm-8">${statusBadge}</div>
        </div>
        
        <div class="recorder-info mt-4">
            <div class="row">
                ${recorderHtml}
                ${updatedInfo}
            </div>
        </div>
    `;
}

// Function to format structured data for display
function formatStructuredData(historyType, data) {
    if (!data || Object.keys(data).length === 0) {
        return '<p class="text-muted fst-italic">No details provided</p>';
    }
    
    let html = '<div class="mt-2">';
    
    switch(historyType) {
        case 'allergies':
            html += formatAllergiesDisplay(data);
            break;
        case 'medications':
            html += formatMedicationsDisplay(data);
            break;
        case 'past_history':
            html += formatPastHistoryDisplay(data);
            break;
        case 'immunization':
            html += formatImmunizationDisplay(data);
            break;
        case 'procedures':
            html += formatProceduresDisplay(data);
            break;
        case 'substance':
            html += formatSubstanceDisplay(data);
            break;
        case 'family':
            html += formatFamilyDisplay(data);
            break;
        case 'menstrual':
            html += formatMenstrualDisplay(data);
            break;
        case 'obstetric':
            html += formatObstetricDisplay(data);
            break;
        case 'growth':
            html += formatGrowthDisplay(data);
            break;
        case 'sexual':
            html += formatSexualDisplay(data);
            break;
        default:
            html += '<pre>' + escapeHtml(JSON.stringify(data, null, 2)) + '</pre>';
    }
    
    html += '</div>';
    return html;
}

function formatAllergiesDisplay(data) {
    let html = '';
    if (data.food && data.food.length > 0) {
        html += `<div class="mb-2"><strong>Food Allergies:</strong> <span class="text-muted">${data.food.join(', ')}</span></div>`;
    }
    if (data.drug && data.drug.length > 0) {
        html += `<div class="mb-2"><strong>Drug Allergies:</strong> <span class="text-muted">${data.drug.join(', ')}</span></div>`;
    }
    if (data.environmental && data.environmental.length > 0) {
        html += `<div class="mb-2"><strong>Environmental Allergies:</strong> <span class="text-muted">${data.environmental.join(', ')}</span></div>`;
    }
    if (data.other && data.other.length > 0) {
        html += `<div class="mb-2"><strong>Other Allergies:</strong> <span class="text-muted">${data.other.join(', ')}</span></div>`;
    }
    if (data.others_text) {
        html += `<div class="mb-2"><strong>Others:</strong> <span class="text-muted">${escapeHtml(data.others_text)}</span></div>`;
    }
    if (data.reaction_type) {
        let reactionTypes = Array.isArray(data.reaction_type) ? data.reaction_type : [data.reaction_type];
        if (reactionTypes.length > 0) {
            html += `<div class="mb-2"><strong>Reaction Type:</strong> <span class="text-muted">${reactionTypes.map(rt => escapeHtml(rt)).join(', ')}</span></div>`;
        }
    }
    if (data.severity) {
        html += `<div class="mb-2"><strong>Severity:</strong> <span class="badge bg-${data.severity === 'Severe' ? 'danger' : data.severity === 'Moderate' ? 'warning' : 'info'}">${escapeHtml(data.severity)}</span></div>`;
    }
    if (data.notes) {
        html += `<div class="mb-2"><strong>Notes:</strong> <span class="text-muted">${escapeHtml(data.notes)}</span></div>`;
    }
    return html || '<p class="text-muted fst-italic">No allergy information provided</p>';
}

function formatMedicationsDisplay(data) {
    if (!Array.isArray(data) || data.length === 0) {
        return '<p class="text-muted fst-italic">No medications recorded</p>';
    }
    let html = '<div class="table-responsive"><table class="table table-sm table-bordered">';
    html += '<thead><tr><th>Medication</th><th>Dosage</th><th>Frequency</th><th>Purpose</th><th>Type</th></tr></thead><tbody>';
    data.forEach(med => {
        html += `<tr>
            <td>${escapeHtml(med.name || '')}</td>
            <td>${escapeHtml(med.dosage || '')}</td>
            <td>${escapeHtml(med.frequency || '')}</td>
            <td>${escapeHtml(med.purpose || '')}</td>
            <td>${med.maintenance ? '<span class="badge bg-primary">Maintenance</span>' : '<span class="badge bg-secondary">As needed</span>'}</td>
        </tr>`;
    });
    html += '</tbody></table></div>';
    return html;
}

function formatPastHistoryDisplay(data) {
    let html = '';
    if (data.conditions && data.conditions.length > 0) {
        html += `<div class="mb-2"><strong>Conditions:</strong> <span class="text-muted">${data.conditions.join(', ')}</span></div>`;
    }
    if (data.others_text) {
        html += `<div class="mb-2"><strong>Others:</strong> <span class="text-muted">${escapeHtml(data.others_text)}</span></div>`;
    }
    if (data.year_diagnosed) {
        html += `<div class="mb-2"><strong>Year Diagnosed:</strong> <span class="text-muted">${escapeHtml(data.year_diagnosed)}</span></div>`;
    }
    if (data.status) {
        html += `<div class="mb-2"><strong>Status:</strong> <span class="badge bg-${data.status === 'Ongoing' ? 'warning' : 'success'}">${escapeHtml(data.status)}</span></div>`;
    }
    if (data.hospitalized) {
        html += `<div class="mb-2"><strong>Hospitalized:</strong> <span class="text-muted">${escapeHtml(data.hospitalized)}</span></div>`;
    }
    if (data.notes) {
        html += `<div class="mb-2"><strong>Notes:</strong> <span class="text-muted">${escapeHtml(data.notes)}</span></div>`;
    }
    return html || '<p class="text-muted fst-italic">No past medical history provided</p>';
}

function formatImmunizationDisplay(data) {
    let html = '';
    if (data.children && data.children.length > 0) {
        html += `<div class="mb-2"><strong>Children Vaccines (EPI):</strong> <span class="text-muted">${data.children.join(', ')}</span></div>`;
    }
    if (data.adults && data.adults.length > 0) {
        html += `<div class="mb-2"><strong>Adult Vaccines:</strong> <span class="text-muted">${data.adults.join(', ')}</span></div>`;
    }
    if (data.covid_brand) {
        html += `<div class="mb-2"><strong>COVID-19 Brand:</strong> <span class="text-muted">${escapeHtml(data.covid_brand)}</span></div>`;
    }
    if (data.covid_doses) {
        html += `<div class="mb-2"><strong>COVID-19 Doses:</strong> <span class="text-muted">${escapeHtml(data.covid_doses)}</span></div>`;
    }
    if (data.last_dose_date) {
        html += `<div class="mb-2"><strong>Last Dose Date:</strong> <span class="text-muted">${escapeHtml(data.last_dose_date)}</span></div>`;
    }
    if (data.status) {
        html += `<div class="mb-2"><strong>Status:</strong> <span class="badge bg-${data.status === 'Completed' ? 'success' : 'warning'}">${escapeHtml(data.status)}</span></div>`;
    }
    if (data.has_card) {
        html += `<div class="mb-2"><strong>Has Vaccination Card:</strong> <span class="text-muted">${escapeHtml(data.has_card)}</span></div>`;
    }
    if (data.notes) {
        html += `<div class="mb-2"><strong>Notes:</strong> <span class="text-muted">${escapeHtml(data.notes)}</span></div>`;
    }
    return html || '<p class="text-muted fst-italic">No immunization information provided</p>';
}

function formatProceduresDisplay(data) {
    if (!Array.isArray(data) || data.length === 0) {
        return '<p class="text-muted fst-italic">No procedures recorded</p>';
    }
    let html = '<ul class="list-group">';
    data.forEach(proc => {
        html += `<li class="list-group-item">
            <strong>${escapeHtml(proc.name || '')}</strong>
            ${proc.year ? ` (${escapeHtml(proc.year)})` : ''}
            ${proc.hospital ? ` - ${escapeHtml(proc.hospital)}` : ''}
        </li>`;
    });
    html += '</ul>';
    return html;
}

function formatSubstanceDisplay(data) {
    let html = '';
    if (data.smoking_status) {
        html += `<div class="mb-2"><strong>Smoking:</strong> <span class="text-muted">${escapeHtml(data.smoking_status)}${data.smoking_packs_year ? ` (${escapeHtml(data.smoking_packs_year)} packs/year)` : ''}</span></div>`;
    }
    if (data.alcohol_status) {
        html += `<div class="mb-2"><strong>Alcohol:</strong> <span class="text-muted">${escapeHtml(data.alcohol_status)}${data.alcohol_type ? ` - ${escapeHtml(data.alcohol_type)}` : ''}</span></div>`;
    }
    if (data.vaping) {
        html += `<div class="mb-2"><strong>Vaping:</strong> <span class="text-muted">${escapeHtml(data.vaping)}</span></div>`;
    }
    if (data.illicit_drugs) {
        html += `<div class="mb-2"><strong>Illicit Drugs:</strong> <span class="text-muted">${escapeHtml(data.illicit_drugs)}</span></div>`;
    }
    if (data.notes) {
        html += `<div class="mb-2"><strong>Notes:</strong> <span class="text-muted">${escapeHtml(data.notes)}</span></div>`;
    }
    return html || '<p class="text-muted fst-italic">No substance use information provided</p>';
}

function formatFamilyDisplay(data) {
    let html = '';
    if (data.conditions && data.conditions.length > 0) {
        html += `<div class="mb-2"><strong>Conditions:</strong> <span class="text-muted">${data.conditions.join(', ')}</span></div>`;
    }
    if (data.relationship) {
        html += `<div class="mb-2"><strong>Relationship:</strong> <span class="text-muted">${escapeHtml(data.relationship)}</span></div>`;
    }
    if (data.status) {
        html += `<div class="mb-2"><strong>Status:</strong> <span class="badge bg-${data.status === 'Alive' ? 'success' : 'secondary'}">${escapeHtml(data.status)}</span></div>`;
    }
    if (data.cause_of_death) {
        html += `<div class="mb-2"><strong>Cause of Death:</strong> <span class="text-muted">${escapeHtml(data.cause_of_death)}</span></div>`;
    }
    if (data.notes) {
        html += `<div class="mb-2"><strong>Notes:</strong> <span class="text-muted">${escapeHtml(data.notes)}</span></div>`;
    }
    return html || '<p class="text-muted fst-italic">No family history provided</p>';
}

function formatMenstrualDisplay(data) {
    let html = '';
    if (data.menarche_age) {
        html += `<div class="mb-2"><strong>Age of Menarche:</strong> <span class="text-muted">${escapeHtml(data.menarche_age)} years</span></div>`;
    }
    if (data.lmp) {
        html += `<div class="mb-2"><strong>LMP (Last Menstrual Period):</strong> <span class="text-muted">${escapeHtml(data.lmp)}</span></div>`;
    }
    if (data.regularity) {
        html += `<div class="mb-2"><strong>Cycle Regularity:</strong> <span class="text-muted">${escapeHtml(data.regularity)}</span></div>`;
    }
    if (data.duration) {
        html += `<div class="mb-2"><strong>Duration:</strong> <span class="text-muted">${escapeHtml(data.duration)} days</span></div>`;
    }
    if (data.dysmenorrhea) {
        html += `<div class="mb-2"><strong>Dysmenorrhea:</strong> <span class="text-muted">${escapeHtml(data.dysmenorrhea)}</span></div>`;
    }
    if (data.notes) {
        html += `<div class="mb-2"><strong>Notes:</strong> <span class="text-muted">${escapeHtml(data.notes)}</span></div>`;
    }
    return html || '<p class="text-muted fst-italic">No menstrual history provided</p>';
}

function formatObstetricDisplay(data) {
    let html = '';
    if (data.gravida || data.para) {
        html += `<div class="mb-2"><strong>Gravida/Para:</strong> <span class="text-muted">G${escapeHtml(data.gravida || '0')}P${escapeHtml(data.para || '0')}</span></div>`;
    }
    if (data.normal_deliveries) {
        html += `<div class="mb-2"><strong>Normal Deliveries:</strong> <span class="text-muted">${escapeHtml(data.normal_deliveries)}</span></div>`;
    }
    if (data.cs) {
        html += `<div class="mb-2"><strong>Cesarean Sections:</strong> <span class="text-muted">${escapeHtml(data.cs)}</span></div>`;
    }
    if (data.miscarriage) {
        html += `<div class="mb-2"><strong>History of Miscarriage:</strong> <span class="text-muted">${escapeHtml(data.miscarriage)}</span></div>`;
    }
    if (data.last_delivery_date) {
        html += `<div class="mb-2"><strong>Last Delivery Date:</strong> <span class="text-muted">${escapeHtml(data.last_delivery_date)}</span></div>`;
    }
    if (data.prenatal_complications) {
        html += `<div class="mb-2"><strong>Prenatal Complications:</strong> <span class="text-muted">${escapeHtml(data.prenatal_complications)}</span></div>`;
    }
    if (data.notes) {
        html += `<div class="mb-2"><strong>Notes:</strong> <span class="text-muted">${escapeHtml(data.notes)}</span></div>`;
    }
    return html || '<p class="text-muted fst-italic">No obstetric history provided</p>';
}

function formatGrowthDisplay(data) {
    let html = '';
    if (data.birth_history) {
        html += `<div class="mb-2"><strong>Birth History:</strong> <span class="text-muted">${escapeHtml(data.birth_history)}</span></div>`;
    }
    if (data.birth_weight) {
        html += `<div class="mb-2"><strong>Birth Weight:</strong> <span class="text-muted">${escapeHtml(data.birth_weight)} kg</span></div>`;
    }
    if (data.milestones) {
        html += `<div class="mb-2"><strong>Developmental Milestones:</strong> <span class="badge bg-${data.milestones === 'Normal' ? 'success' : 'warning'}">${escapeHtml(data.milestones)}</span></div>`;
    }
    if (data.feeding) {
        html += `<div class="mb-2"><strong>Feeding History:</strong> <span class="text-muted">${escapeHtml(data.feeding)}</span></div>`;
    }
    if (data.notes) {
        html += `<div class="mb-2"><strong>Notes:</strong> <span class="text-muted">${escapeHtml(data.notes)}</span></div>`;
    }
    return html || '<p class="text-muted fst-italic">No growth & development information provided</p>';
}

function formatSexualDisplay(data) {
    let html = '';
    if (data.active) {
        html += `<div class="mb-2"><strong>Sexually Active:</strong> <span class="text-muted">${escapeHtml(data.active)}</span></div>`;
    }
    if (data.multiple_partners) {
        html += `<div class="mb-2"><strong>Multiple Partners:</strong> <span class="text-muted">${escapeHtml(data.multiple_partners)}</span></div>`;
    }
    if (data.sti_history) {
        html += `<div class="mb-2"><strong>STI History:</strong> <span class="text-muted">${escapeHtml(data.sti_history)}</span></div>`;
    }
    if (data.notes) {
        html += `<div class="mb-2"><strong>Notes:</strong> <span class="text-muted">${escapeHtml(data.notes)}</span></div>`;
    }
    return html || '<p class="text-muted fst-italic">No sexual history provided</p>';
}

// Function to create a brief preview from structured data
function createStructuredPreview(historyType, data) {
    if (!data || Object.keys(data).length === 0) {
        return '<span class="text-muted fst-italic">No details provided</span>';
    }
    
    let preview = '';
    
    switch(historyType) {
        case 'allergies':
            const allergies = [];
            if (data.food && data.food.length > 0) allergies.push('Food: ' + data.food.slice(0, 2).join(', '));
            if (data.drug && data.drug.length > 0) allergies.push('Drug: ' + data.drug.slice(0, 2).join(', '));
            if (data.environmental && data.environmental.length > 0) allergies.push('Env: ' + data.environmental.slice(0, 2).join(', '));
            preview = allergies.join(' | ') || 'Allergy information';
            break;
        case 'medications':
            if (Array.isArray(data) && data.length > 0) {
                preview = data.slice(0, 2).map(m => m.name || '').filter(Boolean).join(', ');
                if (data.length > 2) preview += '...';
            }
            break;
        case 'past_history':
            if (data.conditions && data.conditions.length > 0) {
                preview = data.conditions.slice(0, 3).join(', ');
                if (data.conditions.length > 3) preview += '...';
            }
            break;
        case 'immunization':
            const vaccines = [];
            if (data.children && data.children.length > 0) vaccines.push(...data.children.slice(0, 2));
            if (data.adults && data.adults.length > 0) vaccines.push(...data.adults.slice(0, 2));
            preview = vaccines.join(', ') || 'Immunization information';
            break;
        case 'procedures':
            if (Array.isArray(data) && data.length > 0) {
                preview = data.slice(0, 2).map(p => p.name || '').filter(Boolean).join(', ');
                if (data.length > 2) preview += '...';
            }
            break;
        case 'substance':
            const items = [];
            if (data.smoking_status) items.push('Smoking: ' + data.smoking_status);
            if (data.alcohol_status) items.push('Alcohol: ' + data.alcohol_status);
            preview = items.join(' | ') || 'Substance use information';
            break;
        case 'family':
            if (data.conditions && data.conditions.length > 0) {
                preview = data.conditions.slice(0, 3).join(', ');
                if (data.relationship) preview += ' (' + data.relationship + ')';
            }
            break;
        case 'menstrual':
            if (data.lmp) preview = 'LMP: ' + data.lmp;
            if (data.regularity) preview += (preview ? ' | ' : '') + 'Regularity: ' + data.regularity;
            break;
        case 'obstetric':
            if (data.gravida || data.para) {
                preview = 'G' + (data.gravida || '0') + 'P' + (data.para || '0');
            }
            break;
        case 'growth':
            if (data.birth_weight) preview = 'Birth Weight: ' + data.birth_weight + ' kg';
            if (data.milestones) preview += (preview ? ' | ' : '') + 'Milestones: ' + data.milestones;
            break;
        case 'sexual':
            if (data.active) preview = 'Sexually Active: ' + data.active;
            break;
        default:
            preview = 'Medical history information';
    }
    
    return escapeHtml(preview) || '<span class="text-muted fst-italic">No details provided</span>';
}

// Helper function to escape HTML (shared by both modals)
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}

// History type labels
const historyTypeLabels = {
    'allergies': 'Allergies',
    'medications': 'Medications',
    'past_history': 'Past Medical History',
    'immunization': 'Immunization/Vaccines',
    'procedures': 'Procedures',
    'substance': 'Substance Used',
    'family': 'Family History',
    'menstrual': 'Menstrual History',
    'sexual': 'Sexual History',
    'obstetric': 'Obstetric History',
    'growth': 'Growth Milestone History'
};

// Check if user is admin or doctor (set from PHP)
const isAdminOrDoctor = <?php echo (isAdmin() || isDoctor()) ? 'true' : 'false'; ?>;
const patientId = <?php echo isset($patient_details['id']) ? $patient_details['id'] : 'null'; ?>;

// Function to show history type modal with all entries
window.showHistoryTypeModal = function(historyType, records) {
    const modalBody = document.getElementById('viewHistoryTypeModalBody');
    const titleSpan = document.getElementById('historyTypeTitle');
    
    // Set title
    const typeLabel = historyTypeLabels[historyType] || historyType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    titleSpan.textContent = typeLabel;
    
    if (!records || records.length === 0) {
        modalBody.innerHTML = `
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No Entries Found</h5>
                <p class="text-muted">There are no ${typeLabel.toLowerCase()} entries for this patient.</p>
            </div>
        `;
    } else {
        let entriesHtml = '';
        
        records.forEach((record, index) => {
            // Format date and time
            const createdDate = record.created_at ? new Date(record.created_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            }) : 'Not available';
            
            const createdTime = record.created_at ? new Date(record.created_at).toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            }) : '';
            
            // Format status
            const status = record.status || 'active';
            const statusBadge = status === 'active' ? 
                '<span class="badge bg-success">Active</span>' : 
                '<span class="badge bg-secondary">Inactive</span>';
            
            // Format recorder info
            let recorderInfo = '';
            let displayName = '';
            let displayRole = '';
            let specialization = '';
            
            if (record.creator_first_name && record.creator_first_name.trim()) {
                displayName = record.creator_first_name + ' ' + (record.creator_last_name || '');
                displayRole = record.creator_role || '';
            } else if (record.doctor_first_name && record.doctor_first_name.trim()) {
                displayName = record.doctor_first_name + ' ' + (record.doctor_last_name || '');
                displayRole = record.doctor_role || '';
                specialization = record.specialization || '';
            }
            
            if (displayName) {
                const roleBadge = displayRole === 'Admin' ? 
                    '<span class="badge bg-warning text-dark me-1">Admin</span>' : 
                    '<span class="badge bg-info text-white me-1">Doctor</span>';
                const specText = specialization ? 
                    ` <span class="text-muted">(${escapeHtml(specialization)})</span>` : '';
                
                recorderInfo = `
                    <div class="col-md-6">
                        <i class="fas fa-user-md me-1"></i>
                        <strong>Recorded by:</strong><br>
                        ${roleBadge}
                        ${escapeHtml(displayName)}${specText}<br>
                        <small class="text-muted">on ${createdDate} ${createdTime ? 'at ' + createdTime : ''}</small>
                    </div>
                `;
            } else {
                recorderInfo = `
                    <div class="col-md-6">
                        <i class="fas fa-user-md me-1"></i>
                        <strong>Recorded by:</strong><br>
                        <span class="text-muted">Unknown Recorder</span><br>
                        <small class="text-muted">on ${createdDate} ${createdTime ? 'at ' + createdTime : ''}</small>
                    </div>
                `;
            }
            
            // Format updated info
            let updatedInfo = '';
            if (record.updated_at && record.created_at && 
                record.updated_at !== record.created_at &&
                record.updater_first_name) {
                const updatedDate = new Date(record.updated_at).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                const updaterRole = record.updater_role === 'Admin' ? 
                    '<span class="badge bg-warning text-dark me-1">Admin</span>' : 
                    '<span class="badge bg-info text-white me-1">Doctor</span>';
                updatedInfo = `
                    <div class="col-md-6 text-md-end">
                        <i class="fas fa-user-edit me-1"></i>
                        <strong>Last updated by:</strong><br>
                        ${updaterRole}
                        ${escapeHtml(record.updater_first_name + ' ' + (record.updater_last_name || ''))}<br>
                        <small class="text-muted">on ${updatedDate}</small>
                    </div>
                `;
            } else {
                updatedInfo = `
                    <div class="col-md-6 text-md-end">
                        <i class="fas fa-calendar me-1"></i>
                        <strong>Date:</strong><br>
                        <small class="text-muted">${createdDate}</small>
                    </div>
                `;
            }
            
            // Preview of details (use structured data if available)
            let detailsPreview = '';
            if (record.structured_data) {
                try {
                    const structuredData = typeof record.structured_data === 'string' 
                        ? JSON.parse(record.structured_data) 
                        : record.structured_data;
                    // Create a brief preview from structured data
                    detailsPreview = createStructuredPreview(record.history_type, structuredData);
                } catch (e) {
                    // Fallback to text details
                    detailsPreview = record.history_details ? 
                        (record.history_details.length > 150 ? 
                            escapeHtml(record.history_details.substring(0, 150)) + '...' : 
                            escapeHtml(record.history_details)) : 
                        '<span class="text-muted fst-italic">No details provided</span>';
                }
            } else {
                detailsPreview = record.history_details ? 
                    (record.history_details.length > 150 ? 
                        escapeHtml(record.history_details.substring(0, 150)) + '...' : 
                        escapeHtml(record.history_details)) : 
                    '<span class="text-muted fst-italic">No details provided</span>';
            }
            
            entriesHtml += `
                <div class="history-entry-item" data-history-id="${record.id}" data-history-json="${escapeHtml(JSON.stringify(record))}">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="mb-1">
                                <span class="badge bg-info text-white me-2">Entry #${index + 1}</span>
                                ${statusBadge}
                            </h6>
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i>${createdDate} ${createdTime ? 'at ' + createdTime : ''}
                            </small>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li>
                                    <a href="#" class="dropdown-item" onclick="showViewMedicalHistoryModal(${record.id}); return false;">
                                        <i class="fas fa-eye me-2"></i>View Details
                                    </a>
                                </li>
                                ${isAdminOrDoctor ? `
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a href="edit_medical_history.php?id=${record.id}&patient_id=${patientId}" class="dropdown-item">
                                            <i class="fas fa-edit me-2"></i>Edit
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#" class="dropdown-item text-danger" onclick="deleteHistoryEntry(${record.id}); return false;">
                                            <i class="fas fa-trash-alt me-2"></i>Delete
                                        </a>
                                    </li>
                                ` : ''}
                            </ul>
                        </div>
                    </div>
                    <div class="mb-2">
                        <strong><i class="fas fa-file-alt me-1"></i>Details:</strong>
                        <div class="text-muted mt-1 p-2 bg-white border rounded">
                            ${detailsPreview}
                        </div>
                    </div>
                    <div class="recorder-info mt-2">
                        <div class="row">
                            ${recorderInfo}
                            ${updatedInfo}
                        </div>
                    </div>
                </div>
            `;
        });
        
        modalBody.innerHTML = `
            <div class="mb-3">
                <p class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Showing ${records.length} ${records.length === 1 ? 'entry' : 'entries'} for ${typeLabel.toLowerCase()}
                </p>
            </div>
            ${entriesHtml}
        `;
    }
    
    // Show the modal
    const modalElement = document.getElementById('viewHistoryTypeModal');
    if (modalElement) {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    }
};

// Function to show modal from card click
function showHistoryTypeModalFromCard(cardElement) {
    const historyType = cardElement.getAttribute('data-history-type');
    const recordsJson = cardElement.getAttribute('data-history-records');
    
    if (!historyType || !recordsJson) {
        showAlert('History data not available', 'Error', 'error');
        return;
    }
    
    try {
        const records = JSON.parse(recordsJson);
        showHistoryTypeModal(historyType, records);
    } catch (e) {
        console.error('Error parsing history records:', e);
        showAlert('Error loading history records', 'Error', 'error');
    }
}

// Function to delete history entry
function deleteHistoryEntry(recordId) {
    confirmDialog('Are you sure you want to delete this history entry?', 'Delete', 'Cancel').then(function(confirmed) {
        if (!confirmed) return;
    
    // Create a form and submit it
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'delete_history_id';
    input.value = recordId;
    
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    });
}

// Function to show the view modal with history data (make it globally accessible)
window.showViewMedicalHistoryModal = function(recordId) {
    // Get the record data from the page (stored in data attribute)
    // Try to find in the history type modal first, then in the main page
    let recordCard = document.querySelector(`#viewHistoryTypeModal [data-history-id="${recordId}"]`);
    if (!recordCard) {
        recordCard = document.querySelector(`[data-history-id="${recordId}"]`);
    }
    
    if (!recordCard) {
        showAlert('History record not found', 'Error', 'error');
        return;
    }
    
    const recordDataJson = recordCard.getAttribute('data-history-json');
    if (!recordDataJson) {
        showAlert('History record data not available', 'Error', 'error');
        return;
    }
    
    try {
        const recordData = JSON.parse(recordDataJson);
        populateViewHistoryModal(recordData);
        
        // Close the history type modal if it's open
        const historyTypeModal = bootstrap.Modal.getInstance(document.getElementById('viewHistoryTypeModal'));
        if (historyTypeModal) {
            historyTypeModal.hide();
        }
        
        // Show the view modal
        const modalElement = document.getElementById('viewMedicalHistoryModal');
        if (modalElement) {
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        } else {
            showAlert('Modal element not found', 'Error', 'error');
        }
    } catch (e) {
        console.error('Error parsing history record data:', e);
        showAlert('Error loading history record data', 'Error', 'error');
    }
};
</script>

<style>
.history-type-badge {
    font-size: 0.8em;
    padding: 4px 8px;
    border-radius: 12px;
    font-weight: 500;
}
.recorder-info {
    background-color: #e8f5e8;
    padding: 8px 12px;
    border-radius: 6px;
    margin-top: 10px;
    border-left: 4px solid #28a745;
}
.history-entry-item {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
    background: #f8f9fa;
    transition: all 0.2s;
}
.history-entry-item:hover {
    background: #e9ecef;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>

