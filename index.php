<?php
require_once 'includes/config.php';

// Pagination
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Sorting
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'download_timestamp';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';
$allowed_columns = ['subject_id', 'collection', 'series_description', 'study_date', 
                   'manufacturer', 'number_of_images', 'file_size', 'modality'];

if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'download_timestamp';
}

// Filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$modality_filter = isset($_GET['modality']) ? $_GET['modality'] : '';
$manufacturer_filter = isset($_GET['manufacturer']) ? $_GET['manufacturer'] : '';

// Build WHERE clause
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(LOWER(subject_id) LIKE LOWER(?) OR LOWER(series_description) LIKE LOWER(?) OR LOWER(collection) LIKE LOWER(?))";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($modality_filter) {
    $where_conditions[] = "modality = ?";
    $params[] = $modality_filter;
}

if ($manufacturer_filter) {
    $where_conditions[] = "manufacturer = ?";
    $params[] = $manufacturer_filter;
}

$where = '';
if (!empty($where_conditions)) {
    $where = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get distinct values for filters
$modalities = $conn->query("SELECT DISTINCT modality FROM studies WHERE modality IS NOT NULL ORDER BY modality");
$manufacturers = $conn->query("SELECT DISTINCT manufacturer FROM studies WHERE manufacturer IS NOT NULL ORDER BY manufacturer");

// Get total records
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM studies $where");
if (!empty($params)) {
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['count'];
$total_pages = ceil($total_records / $limit);

// Get records for current page
$query = "SELECT * FROM studies $where ORDER BY $sort_column $sort_order LIMIT ?, ?";
$stmt = $conn->prepare($query);
$params[] = $offset;
$params[] = $limit;
$stmt->bind_param(str_repeat('s', count($params)), ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Function to generate sort URL
function getSortUrl($column) {
    $params = $_GET;
    $params['sort'] = $column;
    $params['order'] = (isset($_GET['sort']) && 
                       $column === $_GET['sort'] && 
                       isset($_GET['order']) && 
                       $_GET['order'] === 'ASC') ? 'DESC' : 'ASC';
    return '?' . http_build_query($params);
}

// Function to generate filter URL
function getFilterUrl($param_name, $param_value) {
    $params = $_GET;
    if ($param_value === '') {
        unset($params[$param_name]);
    } else {
        $params[$param_name] = $param_value;
    }
    if ($param_name !== 'page') {
        unset($params['page']);
    }
    return '?' . http_build_query($params);
}

// Function to generate sort icon
function getSortIcon($column) {
    if (!isset($_GET['sort'])) {
        return '<i class="fas fa-sort"></i>';
    }
    
    if ($column === $_GET['sort']) {
        $direction = (isset($_GET['order']) && $_GET['order'] === 'ASC') ? 'up' : 'down';
        return '<i class="fas fa-sort-' . $direction . '"></i>';
    }
    
    return '<i class="fas fa-sort"></i>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Images Database</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --success-color: #4cc9f0;
            --warning-color: #f72585;
            --light-bg: #f8f9fa;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        body {
            background: linear-gradient(135deg, #f6f8fd 0%, #f1f4f9 100%);
        }

        .filter-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            margin-bottom: 25px;
            border: 1px solid rgba(67, 97, 238, 0.1);
        }

        .stats-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: var(--card-shadow);
        }

        .stats-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .filter-badge {
            background: #e9ecef;
            padding: 8px 15px;
            border-radius: 20px;
            margin: 3px;
            display: inline-block;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-badge:hover {
            background: var(--accent-color);
            color: white;
        }

        .active-filter {
            background: var(--primary-color);
            color: white;
        }

        .type-icon {
            margin-right: 8px;
            width: 24px;
            text-align: center;
            color: var(--primary-color);
        }

        .table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .table thead th {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 15px;
        }

        .table thead th a {
            color: white !important;
        }

        .table tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }

        .btn-custom-primary {
            background: var(--primary-color);
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-custom-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-1px);
        }

        .range-slider {
            position: relative;
            margin: 15px 0;
        }

        .range-slider input[type="range"] {
            width: 100%;
            height: 8px;
            border-radius: 4px;
            background: #e9ecef;
            outline: none;
            padding: 0;
            margin: 0;
        }

        .range-slider input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary-color);
            cursor: pointer;
            transition: all .3s ease-in-out;
        }

        .range-values {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            color: var(--primary-color);
            font-weight: bold;
        }

        .filter-group {
            background: rgba(67, 97, 238, 0.05);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .filter-group-title {
            color: var(--primary-color);
            font-weight: bold;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .pagination .page-link {
            color: var(--primary-color);
            border: none;
            padding: 10px 15px;
            margin: 0 3px;
            border-radius: 8px;
        }

        .pagination .page-item.active .page-link {
            background: var(--primary-color);
            color: white;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding-left: 40px;
            border-radius: 25px;
            border: 2px solid rgba(67, 97, 238, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid px-4 py-5">
        <div class="row">
            <!-- Sidebar Filters -->
            <div class="col-md-3 p-4">
                <!-- Stats Card -->
                <div class="stats-card">
                    <h5 class="mb-4"><i class="fas fa-chart-bar"></i> Dashboard</h5>
                    <div class="stats-item">
                        <div class="d-flex justify-content-between">
                            <span>Total Studies</span>
                            <strong><?php echo $total_records; ?></strong>
                        </div>
                    </div>
                    <div class="stats-item">
                        <div class="d-flex justify-content-between">
                            <span>Modalities</span>
                            <strong><?php echo $modalities->num_rows; ?></strong>
                        </div>
                    </div>
                    <div class="stats-item">
                        <div class="d-flex justify-content-between">
                            <span>Manufacturers</span>
                            <strong><?php echo $manufacturers->num_rows; ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Filters Section -->
                <div class="filter-section">
                    <form id="filterForm" method="GET" action="">
                        <h5 class="mb-4"><i class="fas fa-filter"></i> Filters</h5>
                        
                        <!-- Search Filter -->
                        <div class="filter-group">
                            <div class="filter-group-title">
                                <i class="fas fa-search type-icon"></i>
                                Search
                            </div>
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Search terms..."
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>

                        <!-- Modality Filter -->
                        <div class="filter-group">
                            <div class="filter-group-title">
                                <i class="fas fa-x-ray type-icon"></i>
                                Image Type
                            </div>
                            <select name="modality" class="form-select">
                                <option value="">All Types</option>
                                <?php 
                                $modalities->data_seek(0);
                                while ($modality = $modalities->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo htmlspecialchars($modality['modality']); ?>"
                                            <?php echo $modality_filter === $modality['modality'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($modality['modality']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Manufacturer Filter -->
                        <div class="filter-group">
                            <div class="filter-group-title">
                                <i class="fas fa-industry type-icon"></i>
                                Manufacturer
                            </div>
                            <select name="manufacturer" class="form-select">
                                <option value="">All Manufacturers</option>
                                <?php 
                                $manufacturers->data_seek(0);
                                while ($manufacturer = $manufacturers->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo htmlspecialchars($manufacturer['manufacturer']); ?>"
                                            <?php echo $manufacturer_filter === $manufacturer['manufacturer'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($manufacturer['manufacturer']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Results per page -->
                        <div class="filter-group">
                            <div class="filter-group-title">
                                <i class="fas fa-list-ol type-icon"></i>
                                Results per page
                            </div>
                            <select name="limit" class="form-select">
                                <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-custom-primary">
                                <i class="fas fa-check"></i> Apply Filters
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-undo"></i> Reset All Filters
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h1 class="h3 mb-0">Medical Images Database</h1>
                    </div>
                    <div class="card-body">
                        <!-- Results Summary -->
                        <div class="alert alert-info">
                            Found <?php echo $total_records; ?> records
                            <?php if ($search || $modality_filter || $manufacturer_filter): ?>
                                with current filters
                            <?php endif; ?>
                        </div>

                        <!-- Data Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>
                                            Patient ID
                                            <a href="<?php echo getSortUrl('subject_id'); ?>" class="text-decoration-none text-white float-end">
                                                <?php echo getSortIcon('subject_id'); ?>
                                            </a>
                                        </th>
                                        <th>
                                            Study Collection
                                            <a href="<?php echo getSortUrl('collection'); ?>" class="text-decoration-none text-white float-end">
                                                <?php echo getSortIcon('collection'); ?>
                                            </a>
                                        </th>
                                        <th>
                                            Description
                                            <a href="<?php echo getSortUrl('series_description'); ?>" class="text-decoration-none text-white float-end">
                                                <?php echo getSortIcon('series_description'); ?>
                                            </a>
                                        </th>
                                        <th>
                                            Image Type
                                            <a href="<?php echo getSortUrl('modality'); ?>" class="text-decoration-none text-white float-end">
                                                <?php echo getSortIcon('modality'); ?>
                                            </a>
                                        </th>
                                        <th>
                                            Image Count
                                            <a href="<?php echo getSortUrl('number_of_images'); ?>" class="text-decoration-none text-white float-end">
                                                <?php echo getSortIcon('number_of_images'); ?>
                                            </a>
                                        </th>
                                        <th>
                                            File Size
                                            <a href="<?php echo getSortUrl('file_size'); ?>" class="text-decoration-none text-white float-end">
                                                <?php echo getSortIcon('file_size'); ?>
                                            </a>
                                        </th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['subject_id']); ?></td>
                                        <td><?php echo htmlspecialchars($row['collection']); ?></td>
                                        <td><?php echo htmlspecialchars($row['series_description']); ?></td>
                                        <td><?php echo htmlspecialchars($row['modality']); ?></td>
                                        <td><?php echo htmlspecialchars($row['number_of_images']); ?></td>
                                        <td><?php echo htmlspecialchars($row['file_size']); ?></td>
                                        <td>
                                            <a href="view.php?id=<?php echo $row['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo getFilterUrl('page', $page - 1); ?>">
                                            Previous
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo getFilterUrl('page', 1); ?>">1</a>
                                    </li>
                                    <?php if ($start_page > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="<?php echo getFilterUrl('page', $i); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo getFilterUrl('page', $total_pages); ?>">
                                            <?php echo $total_pages; ?>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo getFilterUrl('page', $page + 1); ?>">
                                            Next
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterForm = document.getElementById('filterForm');
        
        // Handle form submission
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Remove empty parameters
            const formData = new FormData(filterForm);
            const params = new URLSearchParams();
            
            for (const [key, value] of formData.entries()) {
                if (value.trim() !== '') {
                    params.append(key, value.trim());
                }
            }
            
            // Redirect with new parameters
            window.location.href = '?' + params.toString();
        });
    });
    </script>
</body>
</html>
