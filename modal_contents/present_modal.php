<div class="table-responsive">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Punch In</th>
                <th>Punch Out</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $presentDetailsQuery = "SELECT u.username, a.punch_in, a.punch_out 
                                  FROM attendance a
                                  JOIN users u ON a.user_id = u.id
                                  WHERE a.date = '$formattedDate'
                                  AND a.punch_in IS NOT NULL
                                  ORDER BY a.punch_in ASC";
            $presentDetails = $conn->query($presentDetailsQuery);
            
            if ($presentDetails && $presentDetails->num_rows > 0):
                while ($employee = $presentDetails->fetch_assoc()):
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($employee['username']); ?></td>
                    <td><?php echo date('h:i A', strtotime($employee['punch_in'])); ?></td>
                    <td>
                        <?php 
                        echo $employee['punch_out'] 
                            ? date('h:i A', strtotime($employee['punch_out']))
                            : '<span class="badge bg-success">Still Working</span>';
                        ?>
                    </td>
                </tr>
            <?php 
                endwhile;
            else:
            ?>
                <tr>
                    <td colspan="3" class="text-center">No employees present</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>