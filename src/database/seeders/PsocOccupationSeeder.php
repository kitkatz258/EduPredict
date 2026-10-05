<?php

namespace Database\Seeders;

use App\Models\PsocOccupation;
use Illuminate\Database\Seeder;

/**
 * Starter set. Verify against the official PSA PSOC 2012 before final submission.
 */
class PsocOccupationSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->occupations() as $row) {
            PsocOccupation::query()->updateOrCreate(
                ['psoc_code' => $row['psoc_code']],
                $row,
            );
        }
    }

    /**
     * @return list<array{psoc_code: string, title: string, major_group: string, description: string, skill_tags: list<string>, related_program_codes: list<string>}>
     */
    private function occupations(): array
    {
        $ict = 'Information and communications technology';
        $business = 'Business and administration';
        $education = 'Education';
        $engineering = 'Engineering and technology';
        $health = 'Health';
        $other = 'Other professional services';

        return [
            $this->row('2511', 'Systems Analysts', $ict, 'Study how an organization uses information systems and propose improvements.', ['analysis', 'requirements', 'programming'], ['BSIT', 'BSCS', 'BSIS']),
            $this->row('2512', 'Software Developers', $ict, 'Design, build, and test software applications.', ['programming', 'algorithms', 'sql'], ['BSIT', 'BSCS', 'BSIS']),
            $this->row('2513', 'Web and Multimedia Developers', $ict, 'Build websites and multimedia applications.', ['html', 'programming', 'web'], ['BSIT', 'BSIS']),
            $this->row('2514', 'Applications Programmers', $ict, 'Write and maintain application code from specifications.', ['programming', 'debugging'], ['BSIT', 'BSCS', 'BSIS']),
            $this->row('2519', 'Software and Applications Developers NEC', $ict, 'Software roles that do not fit a narrower developer title.', ['programming'], ['BSIT', 'BSCS', 'BSIS']),
            $this->row('2521', 'Database Designers and Administrators', $ict, 'Design and look after databases and stored data.', ['sql', 'database'], ['BSIT', 'BSCS', 'BSIS']),
            $this->row('2522', 'Systems Administrators', $ict, 'Install and maintain computer systems and servers.', ['systems', 'networks', 'linux'], ['BSIT', 'BSCS', 'BSIS', 'BSCpE']),
            $this->row('2523', 'Computer Network Professionals', $ict, 'Design and support computer networks.', ['networks', 'security'], ['BSIT', 'BSCpE']),
            $this->row('2529', 'Database and Network Professionals NEC', $ict, 'Combined database and network work outside narrower titles.', ['database', 'networks'], ['BSIT', 'BSCS', 'BSIS']),
            $this->row('3512', 'ICT User Support Technicians', $ict, 'Help people use computers, software, and accounts.', ['support', 'troubleshooting'], ['BSIT', 'BSIS']),
            $this->row('3514', 'Web Technicians', $ict, 'Maintain websites and routine web updates.', ['html', 'web'], ['BSIT', 'BSIS']),
            $this->row('1330', 'ICT Service Managers', $ict, 'Plan and supervise information and communications services.', ['management', 'programming'], ['BSIT', 'BSIS', 'BSBA']),

            $this->row('2411', 'Accountants', $business, 'Prepare and examine financial records.', ['accounting', 'audit'], ['BSA', 'BSBA']),
            $this->row('2412', 'Financial and Investment Advisers', $business, 'Advise on budgets, savings, and investments.', ['finance', 'investment'], ['BSA', 'BSBA']),
            $this->row('2421', 'Management and Organization Analysts', $business, 'Review how a team or office is organized and suggest changes.', ['analysis', 'management'], ['BSBA']),
            $this->row('2431', 'Advertising and Marketing Professionals', $business, 'Plan how products and services are presented to audiences.', ['marketing', 'communication'], ['BSBA', 'ABCOMM']),
            $this->row('1221', 'Sales and Marketing Managers', $business, 'Lead sales and marketing work.', ['sales', 'marketing', 'management'], ['BSBA']),
            $this->row('3313', 'Accounting Associate Professionals', $business, 'Support bookkeeping and routine accounting tasks.', ['bookkeeping', 'accounting'], ['BSA', 'BSBA']),
            $this->row('3322', 'Commercial Sales Representatives', $business, 'Present products and services to business customers.', ['sales', 'communication'], ['BSBA']),
            $this->row('4311', 'Accounting and Bookkeeping Clerks', $business, 'Record day-to-day financial transactions.', ['bookkeeping'], ['BSA']),

            $this->row('2310', 'University and Higher Education Teachers', $education, 'Teach courses in a college or university setting.', ['teaching', 'research'], ['BSED']),
            $this->row('2320', 'Vocational Education Teachers', $education, 'Teach job-related subjects in a training setting.', ['teaching', 'training'], ['BSED']),
            $this->row('2330', 'Secondary Education Teachers', $education, 'Teach subjects in a secondary school.', ['teaching', 'lesson planning'], ['BSED']),
            $this->row('2341', 'Primary School Teachers', $education, 'Teach children in an elementary classroom.', ['teaching', 'child development'], ['BEED']),
            $this->row('2351', 'Education Methods Specialists', $education, 'Help schools improve teaching methods and materials.', ['curriculum', 'teaching'], ['BSED', 'BEED']),
            $this->row('2359', 'Teaching Professionals NEC', $education, 'Teaching roles that do not fit a narrower school level.', ['teaching'], ['BSED', 'BEED']),

            $this->row('2142', 'Civil Engineers', $engineering, 'Plan roads, structures, and other civil works.', ['civil design', 'structures'], ['BSCE']),
            $this->row('2144', 'Mechanical Engineers', $engineering, 'Design and test mechanical equipment.', ['mechanical design', 'cad'], ['BSME']),
            $this->row('2149', 'Engineering Professionals NEC', $engineering, 'Engineering work outside a narrower discipline.', ['engineering', 'problem solving'], ['BSCpE']),
            $this->row('2151', 'Electrical Engineers', $engineering, 'Design and maintain electrical systems.', ['electrical', 'circuits'], ['BSEE', 'BSCpE']),
            $this->row('2152', 'Electronics Engineers', $engineering, 'Design electronic devices and embedded systems.', ['electronics', 'circuits'], ['BSCpE']),
            $this->row('2153', 'Telecommunications Engineers', $engineering, 'Plan and support telecommunications systems.', ['networks', 'telecommunications'], ['BSCpE']),
            $this->row('3112', 'Civil Engineering Technicians', $engineering, 'Support civil engineering drawings and site checks.', ['drafting', 'surveying'], ['BSCE']),
            $this->row('3115', 'Mechanical Engineering Technicians', $engineering, 'Support mechanical testing and drawings.', ['drafting', 'mechanical design'], ['BSME']),

            $this->row('2211', 'Generalist Medical Practitioners', $health, 'Provide general medical care.', ['clinical care', 'diagnosis'], ['BSMED']),
            $this->row('2212', 'Specialist Medical Practitioners', $health, 'Provide care in a medical specialty.', ['clinical care', 'specialty medicine'], ['BSMED']),
            $this->row('2221', 'Nursing Professionals', $health, 'Provide nursing care and health education.', ['nursing', 'patient care'], ['BSN']),
            $this->row('2261', 'Dentists', $health, 'Diagnose and treat oral health conditions.', ['dentistry', 'patient care'], ['BSDENT']),
            $this->row('2262', 'Pharmacists', $health, 'Prepare and advise on medicines.', ['pharmacy', 'medication'], ['BSPHARM']),
            $this->row('3211', 'Medical Imaging Technicians', $health, 'Operate imaging equipment used in diagnosis.', ['imaging', 'patient care'], ['BSRT']),
            $this->row('3256', 'Medical Assistants', $health, 'Support clinics with routine patient tasks.', ['clinical support', 'records'], ['BSN']),
            $this->row('5321', 'Health Care Assistants', $health, 'Help patients with daily care under supervision.', ['patient care', 'support'], ['BSN']),

            $this->row('2166', 'Graphic and Multimedia Designers', $other, 'Create visual layouts and multimedia pieces.', ['design', 'multimedia'], ['ABCOMM', 'BSIS']),
            $this->row('2642', 'Journalists', $other, 'Research and present news and features.', ['writing', 'communication'], ['ABCOMM']),
            $this->row('2611', 'Lawyers', $other, 'Advise on legal rights and represent clients.', ['law', 'research'], ['LLB']),
            $this->row('2631', 'Economists', $other, 'Study how resources and markets are used.', ['economics', 'analysis'], ['BSBA']),
            $this->row('2634', 'Psychologists', $other, 'Study behavior for education, workplace, or research settings. This category is not a clinical assessment.', ['assessment', 'communication'], ['BSPSYCH']),
            $this->row('3341', 'Office Supervisors', $other, 'Coordinate clerical work in an office.', ['supervision', 'records'], ['BSBA']),
        ];
    }

    /**
     * @param  list<string>  $skills
     * @param  list<string>  $programs
     * @return array{psoc_code: string, title: string, major_group: string, description: string, skill_tags: list<string>, related_program_codes: list<string>}
     */
    private function row(string $code, string $title, string $group, string $description, array $skills, array $programs): array
    {
        return [
            'psoc_code' => $code,
            'title' => $title,
            'major_group' => $group,
            'description' => $description,
            'skill_tags' => $skills,
            'related_program_codes' => $programs,
        ];
    }
}
